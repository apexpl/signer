<?php
declare(strict_types = 1);

namespace Apex\Signer\Stores;

use Apex\Signer\Models\{Certificate, RsaKey};
use Apex\Signer\Utils\AbstractUtils;

/**
 * Accounts store
 */
class RsaKeyStore extends AbstractUtils
{

    /**
     * List 
     */
    public function list():array
    {

        // Get key files
        $keys = [];
        $files = scandir($this->getConfDir() . '/keys');
        foreach ($files as $file) { 

            if (!preg_match("/^(.+?)\.pem$/", $file, $m)) { 
                continue;
            }
            $keys[] = $m[1];
        }

        // Return
        return $keys;
    }

    /**
     * Get rsa key
     */
    public function get(string $alias, ?string $pem_password = null):RsaKey
    {

        // Get files
        $private_key = file_get_contents($this->getConfDir() . '/keys/' . $alias . '.pem');
        $privkey = $this->unlockPrivateKey($private_key, $pem_password);

        // Create RSA key
        $rsa = new RsaKey(
            alias: $alias,
            private_key: $private_key,
            privkey: $privkey,
            password: $this->pem_password
        );

        // Return
        return $rsa;
    }

    /**
     * Save
     */
    public function save(Certificate $csr):void
    {

        // Save .pem file
        $alias = $csr->getRsaKey()->getAlias();
        if (str_ends_with($csr->getDistinguishedName()->common_name, '@apex')) { 
            $alias .= '.apex';
        }
        $pem_file = $this->getConfDir() . '/keys/' . $alias . '.pem';
        file_put_contents($pem_file, $csr->getRsaKey()->getPrivateKey());

        // Save .crt file
        if ($csr->getCrt() != '') { 
            $crt_file = $this->getConfDir() . '/certs/' . $alias . '.crt';
            file_put_contents($crt_file, $csr->getCrt());
        }

    }

}

