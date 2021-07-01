<?php
declare(strict_types = 1);

namespace Apex\Signer\Cli\Helpers;

use Apex\Signer\Cli\CLi;
use Apex\Signer\Utils\{HttpClient, AbstractUtils};

/**
 * Account utils
 */
class AccountHelper extends AbstractUtils
{

    /**
     * Get an account
     */
    public function get():string
    {

        // Check local accounts
        if (null !== ($username = $this->checkLocalAccounts())) { 
            return $username;
        }

        // Ask if user has Apex account
        $cli = new Cli();
        if (true === $cli->getConfirm("Do you have an existing Apex account you would like to use?")) { 
            return $this->import($cli);
        }

        // Register new account
        $reg_helper = new RegistrationHelper();
        $username = $reg_helper->register();

        // Return
        return $username;
    }

    /**
     * Check local accounts
     */
    private function checkLocalAccounts():?string
    {

        // Scan accounts
        if (!$accounts = $this->scanApexAccounts()) { 
            return null;
        }

        // Get packagist username
        if (!$packagist_username = $this->getPackagistUsername()) { 
            throw new \Exception("Unable to determine Packagist username from composer.json file.  Please ensure your composer.json file has the correct 'name' element.");
        }

        // Display accounts
        $cli = new Cli();
        $cli->send("The following Apex accounts have been detected on this machine.  If you would like to use one of these accounts enter its username below, otherwise leave the field blank and press Enter to register a new Apex account.\r\n\r\n");
        foreach ($accounts as $alias => $vars) { 
            $cli->send("    [$alias] $vars[name]\r\n");
        }
        $cli->send("\r\n");

        // Get alias
        $apex_username = $cli->getInput('Account Username to Use: ');
        if ($apex_username == '' || !isset($accounts[$apex_username])) { 
            return null;
        }
        $sign_key = $accounts[$apex_username]['sign_key'];
        $confdir = rtrim($_SERVER['HOME'], '/') . '/.config/apex';

        // Send http request to claim Packagist username
        $http = new HttpClient();
        $http->authenticate($apex_username, "$confdir/keys/$sign_key.pem");
        $res = $http->send('enduro/packagist/claim', ['username' => $packagist_username]);
        if ((bool) $res['result'] !== true) { 
            throw new \Exception("The Pacakgist username '$packagist_username' has already been claimed by another Apex account.  If you believe this is in error, please contact customer support.");
        }

        // Create symlinks as needed
        $cdir = $this->getConfDir();
        symlink("$confdir/keys/$sign_key.pem", "$cdir/keys/$packagist_username.pem");
        symlink("$confdir/certs/$apex_username.apex.crt", "$cdir/certs/$packagist_username.crt");

        // Return
        return $apex_username;
    }

    /**
     * Display account
     */
    public function display(array $info):void
    {

        // Check e-amil verified
        if ($info['email_verified'] !== true) { 
            $info['email'] .= ' (Unverified)';
        }

        // Check phone verified
        if ($info['phone_verified'] !== true) { 
            $info['phone'] .= ' (Unverified)';
        }

        // Display profile
        $this->cli->sendHeader('Account Profile');
        $this->cli->send("    Username: $info[username]\r\n");
        $this->cli->send("Full Name:  $info[first_name] $info[last_name]\r\n");
        $this->cli->send("    E-Mail Address:  $info[email]\r\n");
        $this->cli->send("    Phone Number:  $info[phone]\r\n");
        $this->cli->send("Date Created:    $info[created_at]\r\n\r\n");

    }

    /**
     * Scan for accounts
     */
    private function scanApexAccounts():?array
    {

        // Check conf dir
        $confdir = rtrim($_SERVER['HOME'], '/') . '/.config/apex';
        if (!is_dir("$confdir/accounts")) { 
            return null;
        }

        // Go through files
        $accounts = [];
        $files = scandir("$confdir/accounts");
        foreach ($files as $file) { 

            // CHeck format
            if (!preg_match("/^([a-zA-Z0-9_-]+?)\.apex\.yml$/", $file, $m)) { 
                continue;
            } elseif (!file_exists("$confdir/certs/$m[1].apex.crt")) { 
                continue;
            }
            $text = file_get_contents("$confdir/accounts/$file");

            // Get signing key
            if (!preg_match("/sign_key:(.+?)\n/", $text, $km)) { 
                continue;
            }
            $sign_key = trim($km[1]);

            // Get cert info
            if (!file_exists("$confdir/keys/$sign_key.pem")) { 
                continue;
            } elseif (!$info = openssl_x509_parse(file_get_contents("$confdir/certs/$m[1].apex.crt"))) { 
                continue;
            }
            $accounts[$m[1]] = [
                'name' => $info['subject']['O'] . ' (' . $info['subject']['OU'] . ') <' . $info['subject']['emailAddress'] . '>', 
                'sign_key' => $sign_key
        ];
        }

        // Return
        return count($accounts) == 0 ? null : $accounts;
    }

    /**
     * Import
     */
    private function import(Cli $cli):?string
    {

        // Send header
        $cli->sendHeader('Import Account');
        $cli->send("You may use your existing Apex account, and to continue enter your username and location of its corresponding private key file below.  If you do not have the private key for the Apex account, you will not be able to use it.\r\n\r\n");
        // Get username
        $username = $cli->getInput('Apex Username: ');

        // Get private key location
        do { 
            $key_file = $cli->getInput('Location of Private Key File: ');
            if (!file_exists($key_file)) { 
                $cli->send("File does not exist, please try again.\r\n\r\n");
                continue;
            }
            break;
        } while (true);

        // Get certificate from ledger
        $http = new HttpClient();
        if (!$res = $http->send('ledger/crt/' . $username . '.apex', [], false, 'GET')) { 
            $cli->error("Unable to find certifiate on ledger for user, $username");
            return null;
        } elseif (!isset($res['certificate'])) { 
            $cli->error("Unable to find certifiate on ledger for user, $username");
            return null;
        }

        // Unlock private key
        $private_key = file_get_contents($key_file);
        $privkey = $this->unlockPrivateKey($private_key);

        // Verify
        if (!openssl_x509_check_private_key($res['certificate'], $privkey)) { 
            $cli->error("Private key does not match the signing certificate for the $username Apex account.");
            return null;
        }

        // Save key files
        $packagist_username = $this->getPackagistUsername();
        file_put_contents($this->getConfDir() . '/keys/' . $packagist_username . '.pem', $private_key);
        file_put_contents($this->getConfDir() . '/certs/' . $packagist_username . '.crt', $res['certificate']);

        // Return
        return $username;
    }

}

