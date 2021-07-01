<?php
declare(strict_types = 1);

namespace Apex\Signer;

use Apex\Signer\Utils\{Signature, Verify};
use Apex\Signer\Models\{MerkleTree, VerificationResult};

/**
 * Signer
 */
class Signer
{

    /**
     * Sign package
     */
    public function sign(string $version, ?string $pem_password = null):MerkleTree
    {
        $sig = new Signature();
        return $sig->sign($version, $pem_password);
    }

    /**
     * Verify
     */
    public function verify():VerificationResult
    {
        $ver = new Verify();
        return $ver->verify();
    }

}



