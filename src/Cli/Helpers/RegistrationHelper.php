<?php
declare(strict_types = 1);

namespace Apex\Signer\Cli\Helpers;

use Apex\Signer\Cli\Cli;
use Apex\Signer\Utils\{HttpClient, AbstractUtils};

/**
 * Registration helper
 */
class RegistrationHelper extends AbstractUtils
{


    /**
     * Register new account
     */
    public function register()
    {

        // Initialize
        $cli = new Cli();

        // Get packagist username
        if (!$packagist_username = $this->getPackagistUsername()) { 
            $cli->error("Unable to determine your Packagist username from the composer.json file.  Please ensure the 'name' element within the composer.json file is correctly set, and try again.");
            exit(0);
        }

        // Check packagist username
        if ($this->checkPackagistUsername($packagist_username) === true) { 
            $cli->error("The Packagist username '$packagist_username' is already assigned to an Apex account.  Please initialize the signer with that Apex account, as a new one can not be created for this Packagist username.");
            exit(0);
        }

        // Send header
        $cli->sendHeader('Account Registration');
        $cli->send("To register a new account, please enter the below fields:\r\n\r\n");

        // Get profile info
        list($username, $email, $password) = $this->getProfileInfo($cli);

        // Generate certificate
        $cert_helper = new CertificateHelper();
        $csr = $cert_helper->generate($username, true);

        // Set request
        $request = [
            'username' => $username, 
            'password' => $password, 
            'email' => $email,
            'pubkey' => $csr->getRsaKey()->getPublicKey(), 
            'csr' => $csr->getCsr(), 
            'ssh_pubkey' => $csr->getRsaKey()->getPublicSshKey(),
            'packagist_username' => $packagist_username
        ];

        // Send request
        $http = new HttpClient();
        $res = $http->send('enduro/users/register', $request);

        // Save .crt file
        $crt_file = $this->getConfDir() . '/certs/' . $username . '.apex.crt';
        file_put_contents($crt_file, $res['crt']);

        // Create symlinks
        $cdir = $this->getConfDir();
        symlink("$cdir/keys/$username.apex.pem", "$cdir/keys/$packagist_username.pem");
        symlink("$cdir/certs/$username.apex.crt", "$cdir/certs/$packagist_username.crt");

        // Success
        $cli->Send("\r\n");
        $cli->send("Thank you, and your account has been successfully registered with the username '$username' and e-mail address '$email'.\r\n\r\n");

        // Return
        return $username;
    }

    /**
     * Check packagist username
     */
    private function checkPackagistUsername(string $packagist_username):bool
    {
        $http = new HttpClient();
        $res = $http->send('enduro/packagist/check', ['username' => $packagist_username]);
            return (bool) $res['exists'];
    }

    /**
     * Get profile info
     */
    private function getProfileInfo(Cli $cli):?array
    {

        // Get username
        do { 
            $username = strtolower($cli->getInput('Desired Username: '));
            if (strlen($username) < 3 || !preg_match("/^[a-zA-z0-9_-]+$/", $username)) { 
                $cli->send("Username must be minimum of 4 characters and can not contain spaces or special characters.  Please try again.\r\n\r\n");
                continue;
            }

            // Check if username exists
            if ($this->checkUsernameExists($username) === true) { 
                $cli->send("Username already exists, $username.  Please try again.\r\n\r\n");
                continue;
            }

            break;
        } while (true);

        // Get e-mail address
        do { 
            $email = strtolower($cli->getInput('E-Mail Address: '));
            if ($email == '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {  
                $cli->send("Invalid e-mail address.\r\n\r\n");
                continue;
            }
            break;
        } while (true);

        // Get password
        $password = $cli->getNewPassword();

        // Return
        $cli->send("\r\n");
        return [$username, $email, $password];
    }

    /**
     * Check username exists
     */
    private function checkUsernameExists(string $username):bool
    {
        $http = new HttpClient();
        $res = $http->send('enduro/users/check_exists', ['username' => $username]);
        return $res['exists'];
    }



}




