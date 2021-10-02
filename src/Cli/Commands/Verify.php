<?php
declare(strict_types = 1);

namespace Apex\Signer\Cli\Commands;

use Apex\Signer\Cli\{Cli, CliHelpScreen};
use Apex\Signer\Utils\Verify as Verifier;
use Apex\Signer\Models\VerificationResult;

/**
 * Verify
 */
class Verify
{

    /**
     * Process
     */
    public function process(Cli $cli, array $args):void
    {

        // Initialize
        global $argv;
        $show_skipped = in_array('--skipped', $argv);

        // Verify
        $verifier = new Verifier();
        $res = $verifier->verify()->toArray();
        $failures = '';

        // Display result
        $cli->sendHeader('Verification Result');
        foreach ($res['packages'] as $pkg_alias => $vars) { 

            // Check if skipped
            if ($vars['status'] == 'skipped') {
                if ($show_skipped === true) { 
                    $cli->send("    $pkg_alias was skipped.\r\n");
                }
                continue;

            // CHeck for failure
            } elseif ($vars['status'] != 'ok') { 
                $failures .= "    $pkg_alias - " . VerificationResult::$fail_reasons[$vars['status']] . "\r\n";

                if ($vars['status'] == 'merkle_mismatch') { 
                    $files = $res['file_mismatches'][$pkg_alias] ?? [];
                    foreach ($files as $file) { 
                        $failures .= "        $file\r\n";
                    }
                    $failures .= "\r\n";
                }
            }

            // Display package
            $status = $vars['status'] == 'ok' ? 'verified' : 'failed (' . $vars['status'] . ')';
            $version = $vars['version'] == 'latest' ? 'latest' : 'v' . $vars['version'];
            $cli->send("    $pkg_alias $version ($vars[num_files] files)... $status\r\n");
        }
        $cli->send("\r\n");

        // Display failures
        if ($res['total_fails'] > 0) { 
            $cli->sendHeader($res['total_fails'] . ' Failures');
            $cli->send("$failures\r\n");
        }
        $cli->send("\r\n");


        // Display note, if  needed
        if ($show_skipped === false) { 
            $cli->send("NOTE: Use the '--skipped' option to see a list of packages skipped, so you can contact the authors and request they add signatures to their releases.  Please tell any necessary authors to visit the URL:\r\n\r\n");
            $cli->send("    https://apexpl.io/composer\r\n\r\n");
        } else {
            $cli->send("Help keep the PHP eco-system secure from outside code injection.  Contact the authors of the skipped packages above, and ask them to visit the URL:\r\n\r\n");
            $cli->send("    https://apexpl.io/composer\r\n\r\n");
        }

        // Get summary
        $summary = $res['total_packages'] . ' verified, ' . $res['total_skipped'] . ' skipped, ' . $res['total_fails'] . ' failures.';
        $cli->send("$summary\r\n\r\n");
    }

    /**
     * Help
     */
    public function help():CliHelpScreen
    {

        $help = new CliHelpScreen(
            title: 'Verify Signatures',
            usage: 'signer verify [--skipped]',
            description: 'Check root package and all packages within /vendor/ directory for signatures.json files, and verifies the digital signatures and certificates.'
        );
        $help->addFlag('--skipped', 'If present will display a list of all skipped packages due to not supporting Apex Signer.');
        $help->addExample('./vendor/bin/signer verify --skipped');

        // Return
        return $help;
    }

}

