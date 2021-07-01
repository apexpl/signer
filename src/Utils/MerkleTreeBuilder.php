<?php
declare(strict_types = 1);

namespace Apex\Signer\Utils;

use Apex\Signer\Models\MerkleTree;

/**
 * Merkel tree builder
 */
class MerkleTreeBuilder
{

    /**
     * Build merkle root
     */
    public function build(array $files, string $rootdir, ?string $prev_merkle_root = null, bool $is_sign = false):MerkleTree
    {

        // Hash files
        $files = $this->hashFiles($files, $rootdir, $is_sign);
        $hashes = array_values($files);

        // Create merkle root
        do { 

            $parents = [];
            while (count($hashes) > 0) { 
                $left = array_shift($hashes);
                $right = count($hashes) > 0 ? array_shift($hashes) : $left;
                $parents[] = hash('sha256', $left . $right);
            }

            // Check if done
            if (count($parents) == 1) {
                $merkle_root = $parents[0];
                break;
            }
            $hashes = $parents;

        } while (true);

        // Hash, if we have prev merkle root
        if ($prev_merkle_root !== null) { 
            $merkle_root = hash('sha256', $prev_merkle_root . $merkle_root);
        }

        // Instantiate merkle tree
        $tree = new MerkleTree( 
            merkle_root: $merkle_root, 
            prev_merkle_root: $prev_merkle_root, 
            files: $files
        );

        // Return
        return $tree;
    }

    /**
     * Build file hashes
     */
    public function hashFiles(array $files, string $rootdir, bool $is_sign = false):array
    {

        // Sort files
        asort($files);

        // Create hashes
        $file_hashes = [];
        foreach ($files as $file) { 

            if ($file == 'signatures.json' || !file_exists("$rootdir/$file")) { 
                continue;
            }

            // Get hash
            if ($is_sign === true) { 
                if (null === ($code = shell_exec("git cat-file --text :$file"))) { 
                    continue;
                }
                $file_hashes[$file] = sha1($code);
            } else { 
                $file_hashes[$file] = sha1_file($rootdir . '/' . $file);
            }
        }

        // Return
        ksort($file_hashes);
        return $file_hashes;
    }

}



