<?php
declare(strict_types = 1);

namespace Apex\Signer\Models;

/**
 * Verification result
 */
class VerificationResult
{

    /**
     * Properties
     */
    private int $total_packages = 0;
    private int $total_fails = 0;
    private int $total_skipped = 0;
    private array $packages = [];
    private array $file_mismatches = [];

    /**
     * Failure reasons
     */
    public static array $fail_reasons = [
        'no_version' => 'Version number not found in signatures.json file.',
        'no_username' => 'Unable to determine Packagist username from composer.json file.',
        'no_cert' => 'Unable to retrieve certificate.',
        'merkle_mismatch' => 'Merkle roots do not match.  The following file hashes do not match:',
        'invalid_sig' => 'Invalid digital signature.',
        'invalid_cert' => 'Invalid x.509 certificate.',
        'invalid_issuer' => 'Invalid certificate issuer.',
        'invalid_packagist_username' => 'Packagist username is not assigned to certificate owner.'
    ];

    /**
     * Add success
     */
    public function addPackage(string $name, string $version, string $status, int $num_files = 0):void
    {

        $this->packages[$name] = [
            'version' => $version,
            'status' => $status,
            'num_files' => $num_files
        ];

        // Add totals
        if ($status == 'ok') { 
            $this->total_packages++;
        } elseif ($status == 'skipped') { 
            $this->total_skipped++;
        } else { 
            $this->total_fails++;
        }

    }

    /**
     * Add file mismatch
     */
    public function addFileMismatch(string $name, string $file):void
    {
        $this->file_mismatches[$name][] = $file;
    }

    /**
     * toArray
     */
    public function toArray():array
    {

        // Get vars
        $vars = [];
        foreach ($this as $key => $value) { 
            $vars[$key] = $value;
        }

    // Return
        return $vars;
    }
}



