<?php
declare(strict_types = 1);

namespace Apex\Signer\Models;

/**
 * Merkle Root
 */
class MerkleTree
{

    /**
     * Constructor
     */
    public function __construct(
        private string $merkle_root, 
        private ?string $prev_merkle_root,
        private array $files,
        private ?string $signature = null
    ) { 

    }

    /**
     * Get merkle root
     */
    public function getMerkleRoot():string
    {
        return $this->merkle_root;
    }

    /**
     * Get prev merkle root
     */
    public function getPrevMerkleRoot():?string
    {
        return $this->prev_merkle_root;
    }

    /**
     * Get files
     */
    public function getFiles():array
    {
        return $this->files;
    }

    /**
     * Get signature
     */
    public function getSignature():?string
    {
        return $this->signature;
    }

    /**
     * Set signature
     */
    public function setSignature(string $sig):void
    {
        $this->signature = $sig;
    }

    /**
     * toArray
     */
    public function toArray():array
    {

        // Set vars
        $vars = [
            'timestamp' => time(), 
            'merkle_root' => $this->merkle_root, 
            'prev_merkle_root' => $this->prev_merkle_root, 
            'files' => $this->files
        ];

        // Return
        return $vars;
    }

}


