<?php
declare(strict_types = 1);

namespace Apex\Signer\Cli\Commands;

use Apex\Signer\Cli\{Cli, CliHelpScreen};

/**
 * Help
 */
class Help
{

    /**
     * Help
     */
    public function help():CliHelpScreen
    {

        // Create help screen
        $help = new CliHelpScreen(
            title: 'Apex Signer',
            usage: 'signer <COMMAND> [OPTIONS]',
            description: 'Sign and verify your Composer packages with digital signatures utilizing x.509 certificates, helping ensure the integrity of your code base.'
        );
        $help->setParamsTitle('AVAILABLE COMMANDS');

        // Set params
        $help->addParam('init', 'Initialize the signer.  Run this if you will be signing releases of this package.');
        $help->addParam('verify', 'Verify digital signatures of the /vendor/ directory.');
        $help->addParam('sign', 'Create digital release signature for this package.  Run just before commit.');
        $help->addParam('release', 'Sign, push and release this package to git repository with one command.');

        // Examples
        $help->addExample('./vendor/bin/signer init');
        $help->addExample('./vendor/bin/signer verify --skipped');
        $help->addExample('./vendor/bin/signer sign 2.1.7');

        // return
        return $help;
    }

}


