<?php
declare(strict_types = 1);

namespace Apex\Signer\Models;

use Apex\Signer\Models\{RsaKey, DistinguishedName};

/**
 * x509 Certificate
 */
class Certificate
{

    /**
     * Constructor
     */
    public function __construct(
        private ?string $crt = null, 
        private ?string $csr = null, 
        private ?RsaKey $rsa_key = null, 
        private ?DistinguishedName $dn = null
    ) { 

    }

    /**
     * Get CRT
     */
    public function getCrt():?string
    {
        return $this->crt;
    }

    /**
     * Get CSR
     */
    public function getCsr():?string
    {
        return $this->csr;
    }

    /**
     * Get RSA key
     */
    public function getRsaKey():?RsaKey
    {
        return $this->rsa_key;
    }

    /**
     * Get distinguished name
     */
    public function getDistinguishedName():?DistinguishedName
    {
        return $this->dn;
    }

    /**
     * Set crt
     */
    public function setCrt(string $crt):void
    {
        $this->crt = $crt;
    }


}


