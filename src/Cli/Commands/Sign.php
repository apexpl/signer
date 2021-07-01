<?php
declare(strict_types = 1);

namespace Apex\Signer\Cli\Commands;

use Apex\Signer\Cli\{Cli, CliHelpScreen};
use Apex\Signer\Utils\{Signature, AbstractUtils};

/**
 * Sign package
 */
class Sign extends AbstractUtils
{

    /**
     8 Process
     */
    public function process(Cli $cli, array $args):void
    {

        // Initialize
        $version = $args[1] ?? '';
        $pem_password = $args[2] ?? null;

        // Get version
        if ($version == '') { 
            $cli->send("Enter the version of this release.  Please note, this must be exactly the same as the git tag you create for this release.\r\n\r\n");
            $version = $cli->getInput('Release Version: ');
        }

        // Sign package
        $signer = new Signature();
        $tree = $signer->sign($version, $pem_password);

        // Get package name
        $pkg_name = $this->getPackagistUsername('', true);

        // Send message
        $cli->sendHeader('Signing Complete');
        $cli->send("Successfully signed new release for this package:\r\n\r\n");
        $cli->send("    Version: $pkg_name v$version\r\n");
        $cli->send("    Merkle Root: " . $tree->getMerkleRoot() . "\n");
        $cli->send("    Partial Signature: " . substr($tree->getSignature(), 0, 32) . "\r\n\r\n");
        $cli->send("Before you commit and push to Github, please ensure you add the signatures.json file with the command: git add signatures.json\r\n\r\n");
        $cli->send("Also please ensure you tag this release in Github with version $version.\r\n\r\n");
    }

    /**
     * Help
     */
    public function help():CliHelpScreen
    {

        $help = new CliHelpScreen(
            title: 'Generate Release Signature',
            usage: 'signer sign [<VERSION>] [<PASSWORD>]',
            description: 'Generates new merkle root and digital signature for a release.  This should be run immediately before you commit and tag a new release, after all necessary files have been staged for commit.'
        );

        // Params
        $help->addParam('version', 'Version number of the release.  This must be exactly the same as the version the git repo will be tagged with.  If unspecified, you will be prompted for the version.');
        $help->addParam('password', 'Optional private key / signing password surpressing any password prompts.');
            $help->addExample('./vendor/bin/signer sign');
        $help->addExample('./vendor/bin/signer sign 2.1.7');

        // Return
        return $help;
    }

}


