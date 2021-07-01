<?php
declare(strict_types = 1);

namespace Apex\Signer\Stores;

use Apex\Signer\Models\MerkleTree;

/**
 * Sig store
 */
class SigStore
{

    /**
     * Properties
     */
    public string $latest;
    public string $crt;
    public ?string $merkle_root = null;
    public array $releases;
    public array $inventory;

    /**
     * Initialize
     */
    public function initialize(string $dir = ''):bool
    {

        // Initialize
        if ($dir == '') { 
            $dir = getcwd();
        }

        // Load signatures.json file
        if (!file_exists("$dir/signatures.json")) { 
            return false;
        } elseif (!$vars = json_decode(file_get_contents("$dir/signatures.json"), true)) { 
            throw new \Exception("Unable to decode signatures.json file, error: " . json_last_error());
        }

        // Set properties
        $this->latest = $vars['latest'] ?? '';
        $this->crt = $vars['crt'];
        $this->releases = $vars['releases'] ?? [];
        $this->inventory = $vars['inventory'] ?? [];

        // Check for merkle root
        if ($this->latest != '' && isset($this->releases[$this->latest])) { 
            $this->merkle_root = $this->releases[$this->latest]['merkle_root'];
        }

        // Return
        return true;
    }

    /**
     * Add release
     */
    public function addRelease(string $version, string $signature, MerkleTree $tree):void
    {

        // Get prev merkle root
        if (null === ($prev_merkle_root = $tree->getPrevMerkleRoot())) { 
            $prev_merkle_root = '';
        }

        $this->releases[$version] = [
            'timestamp' => time(),
            'merkle_root' => $tree->getMerkleRoot(),
            'prev_merkle_root' => $prev_merkle_root,
            'signature' => $signature
        ];
        $this->inventory = $tree->getFiles();
        $this->latest = $version;

        // Save
        $this->save();
    }

    /**
     * Save file
     */
    public function save():void
    {

        // Set json
        $json = [
            'readme' => "This file contains all digital signatures for this package created by Apex Signer (https://github.com/apexpl/signer/), is automatically generated, and should not be manually modified.",
            'crt' => $this->crt,
            'latest' => $this->latest,
        'releases' => $this->releases,
            'inventory' => $this->inventory
        ];

        // Save JSON file
        file_put_contents(getcwd() . '/signatures.json', json_encode($json, JSON_PRETTY_PRINT));
    }


}


