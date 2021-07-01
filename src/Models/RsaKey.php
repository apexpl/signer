<?php
declare(strict_types = 1);

namespace Apex\Signer\Models;

use OpenSSLAsymmetricKey;

/**
 * RSA Key
 */
class RsaKey
{

    /**
     * Constructor
     */
    public function __construct(
        private ?string $alias = null, 
        private ?OpenSSLAsymmetricKey $privkey = null, 
        private ?string $password = null, 
        private ?string $public_key = null, 
        private ?string $private_key = null 
    ) { 

    }

    /**
     * Get alias
     */
    public function getAlias():?string
    {
        return $this->alias;
    }

    /**
     * Get public key
     */
    public function getPublicKey():?string
    {

        // Get public key, if needed
        if ($this->public_key === null && $this->privkey !== null) { 
            $details = openssl_pkey_get_details($this->privkey);
            $this->public_key = $details['key'];
        }
        return $this->public_key;
    }

    /**
     * Get private key
     */
    public function getPrivateKey():?string
    {

        // Export key, if needed
        if ($this->private_key === null && $this->privkey !== null) { 
            openssl_pkey_export($this->privkey, $privkey_out, $this->password);
            $this->private_key = $privkey_out;
        }
        return $this->private_key;
    }

    /**
     * Get password
     */
    public function getPassword():?string
    {
        return $this->password;
    }

    /**
     * Get loaded privkey
     */
    public function getPrivkey():?OpenSSLAsymmetricKey
    {
        return $this->privkey;
    }

    /**
     * Get public SSH key
     */
    public function getPublicSshKey():string
    {

        $keyInfo = openssl_pkey_get_details($this->privkey);
        $buffer = pack("N", 7) . "ssh-rsa" .
            $this->sshEncodeBuffer($keyInfo['rsa']['e']) . 
            $this->sshEncodeBuffer($keyInfo['rsa']['n']);

        // Return
        return "ssh-rsa " . base64_encode($buffer);
    }

    /**
     * Encode SSH buffer
     */
    public function sshEncodeBuffer($buffer) 
    {
        $len = strlen($buffer);
        if (ord($buffer[0]) & 0x80) {
            $len++;
            $buffer = "\x00" . $buffer;
        }
        return pack("Na*", $len, $buffer);
    }

    /**
     * Set alias
     */
    public function setAlias(string $alias):void
    {
        $this->alias = $alias;
    }

    /**
     * Set privkey
     */
    public function setPrivkey(OpenSSLAsymmetricKey $key):void
    {
        $this->privkey = $key;
    }

    /**
     * Set password
     */
    public function setPassword(?string $password):void
    {
        $this->password = $password;
    }

}


