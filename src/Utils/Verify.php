<?php
declare(strict_types = 1);

namespace Apex\Signer\Utils;

use Apex\Signer\Cli\Cli;
use Apex\Signer\Stores\SigStore;
use Apex\Signer\Models\VerificationResult;

/**
 * Verification
 */
class Verify extends AbstractUtils
{

    // Properties
    private ?string $issuer_crt = null;
    private array $fingerprints = [];

    /**
     * Verify
     */
    public function verify():VerificationResult
    {

        // Initialize
        $this->res = new VerificationResult();

        // Get composer.lock file
        if (!file_exists(getcwd() . '/composer.lock')) { 
            throw new \Exception("No composer.lock file exists.");
        } elseif (!$json = json_decode(file_get_contents(getcwd() . '/composer.lock'), true)) { 
            throw new \Exception("Unable to load composer.lock file, error: " . json_last_error());
        }

        // Verify root, if has signatures.json
        if (file_exists(getcwd() . '/signatures.json')) { 
            $this->verifyPackage('/', 'latest');
        }

        // Go through packages
        foreach ($json['packages'] as $pkg) { 
            $name = $pkg['name'] ?? '';
            $version = $pkg['version'] ?? '';

            // Check exists
            if (!is_dir(getcwd() . '/vendor/' . $name)) { 
                continue;
            } elseif (!file_exists(getcwd() . '/vendor/' . $name . '/signatures.json')) {
                $this->res->addPackage($name, $version, 'skipped');
                continue;
            }

            // Verify package
            $this->verifyPackage($name, $version);
        }

        // Return
        return $this->res;
    }

    /**
     * Verify package
     */
    private function verifyPackage(string $name, string $version):bool
    {

        // Initialize
        $dir = getcwd();
        if ($name != '/') { 
            $dir .= '/vendor/' . $name;
        }
        $display_name = $name == '/' ? $this->getPackagistUsername('', true) : $name;

        // Load signature store
        $store = new SigStore();
        if (!$store->initialize($dir)) { 
            $this->res->addPackage($display_name, $version, 'skipped');
            return false;
        } elseif ($version != 'latest' && !isset($store->releases[$version])) { 
            $this->res->addPackage($display_name, $version, 'no_version');
            return false;
        } elseif ($version == 'latest' && !isset($store->releases[$store->latest])) { 
            $this->res->addPackage($display_name, $version, 'no_version');
            return false;
        }

        // Set variables
        $rel = $version == 'latest' ? $store->releases[$store->latest] : $store->releases[$version];
        $prev_merkle_root = $rel['prev_merkle_root'] == '' ? null : $rel['prev_merkle_root'];
        $num_files = count($store->inventory);

        // Get Packagist username
        if (!$username = $this->getPackagistUsername($dir)) { 
            $this->res->addPackage($display_name, $version, 'no_username', $num_files);
            return false;
        }

        // Get certificate
        if (file_exists($this->getConfDir() . '/certs/' . $username . '.crt')) { 
            $crt_text = file_get_contents($this->getConfDir() . '/certs/' . $username . '.crt');
        } elseif (isset($store->crt) && $store->crt != '') { 
            $crt_text = $store->crt;
            file_put_contents($this->getConfDir() . '/certs/' . $username . '.crt', $store->crt);
        } else {
            $this->res->addPackage($display_name, $version, 'no_cert', $num_files);
            return false;
        }
        $crt = openssl_x509_read($crt_text);

        // Build merkle root
        $tree_builder = new MerkleTreeBuilder();
        $tree = $tree_builder->build(array_keys($store->inventory), $dir, $prev_merkle_root);

        // Check merkle root
        if ($tree->getMerkleRoot() != $rel['merkle_root']) { 
            $this->res->addPackage($display_name, $version, 'merkle_mismatch', $num_files);
            $this->compareInventory($display_name, $store->inventory, $tree->getFiles());
            return false;
        } elseif (1 !== openssl_verify($rel['merkle_root'], hex2bin($rel['signature']), $crt, 'sha384')) { 
            $this->res->addPackage($display_name, $version, 'invalid_sig', $num_files);
            return false;
        } 

        // Check certificate
        $status = $this->verifyCertificate($crt_text, $username, (int) $rel['timestamp']);
        $this->res->addPackage($display_name, $version, $status, $num_files);

        // Return
        return $status == 'ok' ? true : false;
    }

    /**
     * Verify certificate
     */
    private function verifyCertificate(string $crt_text, string $username, int $timestamp):string
    {

        // Check common name
        if (!$details = openssl_x509_parse($crt_text)) { 
            return 'invalid_cert';
        } elseif (!preg_match("/^([a-zA-Z0-9_-]+)\@([a-zA-Z0-9_-]+)$/", $details['subject']['CN'], $m)) { 
            return 'invalid_cert';
        } elseif ($m[2] == 'packagist' && $username != $m[1]) { 
            return 'invalid_cert';
        }

        // Return if Packagist certificate
        if ($m[2] == 'packagist') { 
            return 'ok';
        } elseif ($m[2] != 'apex') { 
            return 'invalid_cert';
        }
        $crt_name = str_replace('@', '.', $m[0]);

        // Get issuer crt
        $issuer_crt = $this->getIssuerCertificate();
        if (1 !== openssl_x509_verify($crt_text, $issuer_crt)) { 
            return 'invalid_issuer';
        }

        // Check for validated crt
        $fingerprint = openssl_x509_fingerprint($crt_text, 'sha384');
        $chk_certs = $this->fingerprints[$fingerprint] ?? [];
        if (in_array($username, $chk_certs)) { 
            return 'ok';
        }

        // Set request
        $request = [
            'packagist_username' => $username,
            'release_date' => date('Y-m-d H:i:s', $timestamp),
            'fingerprints' => [
                $crt_name => $fingerprint,
                'ca.apex' => openssl_x509_fingerprint($issuer_crt, 'sha384')
            ]
        ];

        // Send http request
        $http = new HttpClient();
        $res = $http->send('ledger/verify_certificate', $request, true);
        if ($res['fail'] > 0) { 
            return 'invalid_cert';
        } elseif ((bool) $res['packagist_check'] !== true) { 
            return 'invalid_packagist_username';
        }

        // Return
        return 'ok';
    }

    /**
     * Get issuer crt
     */
    private function getIssuerCertificate():string
    {

        // Check if we have
        if ($this->issuer_crt !== null) { 
            return $this->issuer_crt;
        }

        // Check for file
        $crt_file = $this->getConfDir() . '/certs/ca.apex.crt';
        if (file_exists($crt_file)) { 
            $this->issuer_crt = file_get_contents($crt_file);
            return $this->issuer_crt;
        }

        // SEnd http request
        $http = new HttpClient();
        if (!$res = $http->send('ledger/crt/ca.apex', [], false, 'GET')) { 
            throw new \Exception("Unable to retrieve ca.apex certificate from online ledger.");
        }
        file_put_contents($crt_file, $res['certificate']);

        // Return
        $this->issuer_crt = $res['certificate'];
        return $res['certificate'];
    }

    /**
     * Compare inventory
     */
    private function compareInventory(string $name, array $remote, array $local):void
    {

        // Add mismatched files
        $diff = array_diff($remote, $local);
        foreach ($diff as $file => $hash) { 
            $this->res->addFileMismatch($name, $file);
        }


    }

}


