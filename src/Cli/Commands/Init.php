<?php
declare(strict_types = 1);

namespace Apex\Signer\Cli\Commands;

use Apex\Signer\Cli\{Cli, CliHelpScreen};
use Apex\Signer\Stores\RsaKeyStore;
use Apex\Signer\Cli\Helpers\{CertificateHelper, AccountHelper};
use Apex\Signer\Utils\AbstractUtils;

/**
 * Init signer
 */
class Init extends AbstractUtils
{

    /**
     * Process
     */
    public function process(Cli $cli, array $args):void
    {

        // Check directory
        $dir = getcwd();
        if (!file_exists("$dir/composer.json")) { 
            $cli->error("Unable to find composer.json file in this directory.  Please run 'signer' from your package's root directory where composer.json resides.");
            return;
        } elseif (!is_dir("$dir/.git")) { 
            $cli->error("This directory has not yet been initialized by git, hence can not be used by the 'signer'.  Please initialize via git first.");
            return;
        } elseif (file_exists("$dir/signatures.json")) { 
            $cli->error("This directory has already been initialized.  If desired, you may delete the signatures.json file from this directory and run this command again to reinitialize the directory.");
            return;
        }

        
        // Get key alias to use
        $alias = $this->getKeyAlias($cli);

        // Save signatures.json file
        $json = [
            'readme' => "This file contains all digital signatures for this package created by Apex Signer (https://github.com/apexpl/signer/), is automatically generated, and should not be manually modified.",
            'crt' => file_get_contents($this->getConfDir() . '/certs/' . $alias . '.crt'),
            'latest' => '',
            'releases' => [],
            'inventory' => []
        ];
        file_put_contents(getcwd() . '/signatures.json', json_encode($json, JSON_PRETTY_PRINT));

        // Send header
        $cli->send("\r\n");
        $cli->sendHeader('Signer Successfully Initialized');

        // Success message
        $cli->send("Successfully initialized the signer, and you may now sign releases of this package with the command:\r\n\r\n");
        $cli->send("    ./vendor/bin/signer sign <VERSION>\r\n\r\n");
        if (file_exists(getcwd() . '/' . $alias . '.pem')) { 
            $cli->send("A new RSA private key has been generated for you, and can be found within this directory at $alias.pem.  Please make a backup of this file, then delete it from this directory as it is not needed at this location.\r\n\r\n");
            $cli->send("NOTE: The private key is important, only you have a copy of it, it was generated locally on this machine, and it it can not be retrieved later.  Please keep a safe backup of the file and ensure you do NOT add it to your Github repository.\r\n\r\n");
        }

    }

    /**
     * Get key alias
     */
    private function getKeyAlias(Cli $cli):string
    {

        // Get packagist username
        if (!$username = $this->getPackagistUsername()) { 
            $cli->error("Unable to obtain the Packagist username from the composer.json file.  Please ensure the 'name' element is correctly set within the composer.json file, and try again.");
            exit(0);
        }

        // Check existing keys
        if ($this->checkExistingKeys($cli, $username) === true) { 
            return $username;
        }

        // Set options
        $options = [
            'online' => 'Publish to public ledger at https://ledger.apexpl.io/',
            'offline' => 'Keep signatures within local signatures.json file only'
        ];

        // Send greeting
        $cli->sendHeader('Initialize Apex Signer');
        $cli->send("This will initialize the signer and ready it for signing all future releases of your Composer package.  You may choose to either, publish your digital signatures to the public ledger at https://ledger.apexpl.io/ for greater verification and security, or keep the digital signatures only within the local signatures.json file.\r\n\r\n");
        $cli->send("It is recommended that you publish signatures to the public ledger as it provides additional certificate verification, plus allows users of your package to be automatically notified when a new release is tagged.\r\n\r\n");
        $sign_type = $cli->getOption("Which method would you like to use?", $options, '', true);

        // Get Apex account if online signing
        if ($sign_type == 'online') { 
            $acct_helper = new AccountHelper();
            $apex_username = $acct_helper->get();
            return $username;
        }

        // Generate new certificate
        $cert_helper = new CertificateHelper();
        $csr = $cert_helper->generate($username, false);

        // Return
        return $csr->getRsaKey()->getAlias();
    }

    /**
     * Check existing accounts
     */
    private function checkExistingKeys(Cli $cli, string $username):bool
    {

        // Get existing keys
        $store = new RsaKeyStore($cli);
        $keys = $store->list();

        // Return
        return in_array($username, $keys);
    }

    /**
     * Help
     */
    public function help():CliHelpScreen
    {

        $help = new CliHelpScreen(
            title: 'Initialize Signer',
            usage: 'signer init',
            description: 'Initializes the signer, and readies the directory for signing of all future releases.  This must be run within the root directory of your package, in the same directory where the composer.json file resides.'
        );
        $help->addExample('./vendor/bin/signer init');

        // Return
        return $help;
    }


}

