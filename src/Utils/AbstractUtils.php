<?php
declare(strict_types = 1);

namespace Apex\Signer\Utils;

use Apex\Signer\Cli\Cli;
use OpenSSLAsymmetricKey;

/**
 * Abstract utils
 */
class AbstractUtils
{

    // Properties
    protected ?string $pem_password = null;

    /**
     * Get conf dir
     */
    protected function getConfDir():string
    {

        // Get dir
        $dir = rtrim($_SERVER['HOME'], '/') . '/.config/apex-signer';
        if (isset($_SERVER['SCRIPT_NAME']) && str_contains($_SERVER['SCRIPT_NAME'], 'phpunit')) {
            $dir .= '-test';
        }

        // Return
        return $dir;
    }

    /**
     * Unlock private key
     */
    protected function unlockPrivateKey(string $private_key, ?string $pem_password = null):OpenSSLAsymmetricKey
    {

        // Try without password
        if ($privkey = openssl_pkey_get_private($private_key)) { 
            return $privkey;
        } elseif ($pem_password !== null && $privkey = openssl_pkey_get_private($private_key, $pem_password)) { 
            return $privkey;
        }
        $cli = new Cli();

        // Get password
        do { 
            $password = $cli->getInput('Signing Password: ', '', true);
            if (!$privkey = openssl_pkey_get_private($private_key, $password)) { 
                $cli->send("Invalid password, please try again.\r\n\r\n");
                continue;
            }
            $this->pem_password = $password;
            break;
        } while (true);

        // Return
        return $privkey;
    }

    /**
     * Get packagist username
     */
    protected function getPackagistUsername(string $dir = '', bool $return_name = false):?string
    {

        // Initialize
        if ($dir == '') { 
            $dir = getcwd();
        }

        // Load composer.json file
        if (!file_exists("$dir/composer.json")) { 
            return null;
        } elseif (!$json = json_decode(file_get_contents("$dir/composer.json"), true)) { 
            return null;
        } elseif (!isset($json['name'])) { 
            return null;
        } elseif (!preg_match("/^(.+?)\/(.+)$/", $json['name'], $m)) { 
            return null;
        }

        // Return
        return $return_name === true ? $m[0] : $m[1];
    }

}

