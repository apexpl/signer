<?php
declare(strict_types = 1);

namespace Apex\Signer\Utils;

use Apex\Signer\Cli\Cli;
use Apex\Signer\Models\{MerkleTree, RsaKey};
use Apex\Signer\Stores\{SigStore, RsaKeyStore};

/**
 * Signer
 */
class Signature extends AbstractUtils
{

    /**
     * Create signature
     */
    public function sign(string $version, ?string $pem_password = null):MerkleTree
    {

        // Initialize
        $cli = new Cli();

        // Load signatures.json file
        $sig_store = new SigStore($cli);
        if (true !== ($sig_store->initialize())) { 
            return null;
        }

        // Get files from git
        exec("git ls-files", $files);
        if (count($files) == 0) { 
            throw new \Exception("Unable to obtain file list from git.  Please ensure the 'git' command is available within the environment path from this directory.");
        }

        // Get packagist username
        if (!$username = $this->getPackagistUsername()) { 
            throw new \Exception("Unable to determine Packagist username from composer.json file.  Please ensure the 'name' element within the composer.json file is properly set.");
        }

        // Get x.509 certificate
        $crt_file = $this->getConfDir() . '/certs/' . $username . '.crt';
        if (!file_exists($crt_file)) { 
            throw new \Exception("Certificate file does not exist at, $crt_file");
        } elseif (!$crt = openssl_x509_parse(file_get_contents($crt_file))) { 
            throw new \Exception("Unable to read certificate file at, $crt_file");
        }

        // Get RSA key
        $key_store = new RsaKeyStore($cli);
        $rsa = $key_store->get($username, $pem_password);
        $privkey = $rsa->getPrivkey();

        // Build merkle tree
        $tree_builder = new MerkleTreeBuilder();
        $tree = $tree_builder->build($files, getcwd(), $sig_store->merkle_root, true);

        // Sign merkle root
        openssl_sign($tree->getMerkleRoot(), $signature, $privkey, 'sha384');
        $signature = bin2hex($signature);
        $tree->setSignature($signature);

        // Publish release, if we're doing online
        if (preg_match("/^(.+?)\@apex$/", $crt['subject']['CN'], $m)) { 
            $this->publish($version, $m[1], $rsa, $tree);
        }

        // Save release
        $sig_store->addRelease($version, $signature, $tree);

        // Return
        return $tree;
    }

    /**
     * Publish release to online ledger
     */
    private function publish(string $version, string $username, RsaKey $rsa, MerkleTree $tree):void
    {

        // authenticate
        $http = new HttpClient();
        $http->authenticate($username, '', $rsa->getPassword());

        // Set request
        $request = [
            'common_name' => $username . '@apex',
            'pkg_serial' => $this->getPackagistUsername('', true),
            'repo_alias' => 'packagist',
            'version' => $version,
            'signature' => $tree->getSignature(),
            'merkle_root' => $tree->getMerkleRoot(),
            'prev_merkle_root' => $tree->getPrevMerkleRoot()
        ];

        // Publish release
        if (!$res = $http->send('ledger/releases/add', $request)) { 
            throw new \Exception("Unable to publish release to online ledger, message: $res[message]");
        }

    }


}


