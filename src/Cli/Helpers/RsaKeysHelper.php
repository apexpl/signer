<?php
declare(strict_types = 1);

namespace Apex\Signer\Cli\Helpers;

use Apex\Signer\Cli\Cli;
use Apex\Signer\Utils\AbstractUtils;
use Apex\Signer\Models\RsaKey;
use OpenSSLAsymmetricKey;

/**
 * RSA / SSH Keys
 */
class RsaKeysHelper extends AbstractUtils
{

    // Properties
    private ?string $password = null;

    /**
     * Get SSH key
     */
    public function get(string $username, bool $is_apex = false):RsaKey
    {

        // Initialize
        $cli = new Cli();
        $this->cli = $cli;

        // Ask for PEM file
        $cli->send("If you have an existing RSA private key you would like to use, please enter the location of the PEM file below.  Otherwise, leave the field blank and press Enter to generate a new private key.\r\n\r\n");
        $pem_file = $cli->getInput('Location of PEM File: ');
        if ($pem_file == '') { 
            $rsa = $this->generate($username, $is_apex);
            return $rsa;
        }

        // Check file exists
        if (!file_exists($pem_file)) { 
            $cli->error("PEM file does not exist at, $pem_file");
            exit(0);
        }

        // Unlock private key
        $private_key = file_get_contents($pem_file);
        $privkey = $this->unlockPrivateKey($private_key);

        // Get RSA key
        $rsa = new RsaKey(
            privkey: $privkey,
            private_key: $private_key,
        );

        // Return
        return $rsa;
    }

    /**
     * Generate new SSH key
     */
    private function generate(string $username, bool $is_apex = false):RsaKey
    {

        // Get password
        $this->cli->sendHeader('Signing Password');
        $this->cli->send("A new 4096 bit RSA key will now be generated, and please enter the desired password below.  You may leave this blank and press Enter twice to create the private key without a password.\r\n\r\n");
        $password = $this->cli->getNewPassword('Signing Password', true);
        $this->cli->send("\r\n");

        // Generate private key
        $privkey = openssl_pkey_new([
            "digest_alg" => "sha512",
            "private_key_bits" => 4096,
            "private_key_type" => OPENSSL_KEYTYPE_RSA 
        ]);

        // Get new RSA key
        $rsa = new RsaKey(
            privkey: $privkey, 
            password: $password
        );

        // Get packagist username
        $packagist_username = $this->getPackagistUsername();

        // Save to cwd
        $filename = getcwd() . '/' . $packagist_username . '.pem';
        file_put_contents($filename, $rsa->getPrivateKey());

        // Return
        return $rsa;
    }

    /**
     * Check Apex accounts
     */
    private function checkApexAccounts():?RsaKey
    {

        // Check if dir exists
        $confdir = rtrim($_SERVER['HOME'], '/') . '/.config/apex/keys';
        if (!is_dir($confdir)) { 
            return null;
        }

        // Get keys
        $keys = [];
        $files = scandir($confdir);
        foreach ($files as $file) { 

            // Skip, if needed
            if (!preg_match("/^(.+?)\.pem$/", $file, $m)) { 
                continue;
            }

            // Get name
            $info = stat("$confdir/$file");
            $keys[$m[1]] = 'Created: ' . date('Y-m-d H:i', $info['ctime']);

            // Check if password protected
            $text = file_get_contents("$confdir/$file");
            if (!openssl_pkey_get_private($text)) {
                $keys[$m[1]] .= ' (Password Protected)';
            }
        }

        // Return if no keys found
        if (count($keys) == 0) { 
            return null;
        }

        // Send
        $this->cli->send("The following Apex account keys have been detected on this machine:\r\n\r\n");
        foreach ($keys as $alias => $name) { 
            $this->cli->send("    [$alias] $name\r\n");
        }
        $this->cli->send("\r\n");
        $this->cli->send("If you would like to use one of the above keys, enter its alias below.  Otherwise, leave the field blank and press Enter to continue.\r\n\r\n");

        // Get alias
        $alias = $this->cli->getInput('Key Alias []: ');
        if ($alias == '') { 
            return null;
        } elseif (!file_exists($confdir . '/' . $alias . '.pem')) { 
            $this->cli->send("Invalid key alias, $alias.  Continuing without one.\r\n\r\n");
            return null;
        }

        // Unlock private key
        $private_key = file_get_contents($confdir . '/' . $alias . '.pem');
        $privkey = $this->unlockPrivateKey($private_key);

        // Create rsa key
        $rsa = new RsaKey(
            privkey: $privkey,
            private_key: $private_key,
        );

        // Return
        return $rsa;
    }

}


