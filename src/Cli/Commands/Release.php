<?php
declare(strict_types = 1);

namespace Apex\Signer\Cli\Commands;

use Apex\Signer\Cli\{Cli, CliHelpScreen};
use Apex\Signer\Utils\{Signature, AbstractUtils};

/**
 * Sign package
 */
class Release extends AbstractUtils
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

        // Get Github info
        if (!list($remote, $branch, $commit_args) = $this->getGitInfo($cli)) { 
            return;
        }

        // Sign package
        $signer = new Signature();
        $tree = $signer->sign($version, $pem_password);

        // Release package
        passthru("git add signatures.json");
        passthru("git commit $commit_args");
        passthru("git push -u $remote $branch");
        passthru("git tag $version");
        passthru("git push --tags");

        // Send message
        $pkg_name = $this->getPackagistUsername('', true);
        $cli->sendHeader('Signing Complete');
        $cli->send("Successfully signed new release for this package:\r\n\r\n");
        $cli->send("    Version: $pkg_name v$version\r\n");
        $cli->send("    Merkle Root: " . $tree->getMerkleRoot() . "\n");
        $cli->send("    Partial Signature: " . substr($tree->getSignature(), 0, 32) . "\r\n\r\n");
        $cli->send("Before you commit and push to Github, please ensure you add the signatures.json file with the command: git add signatures.json\r\n\r\n");
        $cli->send("Also please ensure you tag this release in Github with version $version.\r\n\r\n");
    }

    /**
     * Get git info
     */
    private function getGitInfo(Cli $cli):?array
    {

        // Initialize
        global $argv;
        $args = $argv;
        $commit_args = null;

        if (!file_exists(getcwd() . '/signatures.json')) { 
            $cli->error("This directory has not yet been initialized.  Please first do so via the 'signer init' command.");
            return null;
        }

        // Get commit args
        if (false !== ($key = array_search('-m', $args))) { 
            $commit_args = '-m "' . $args[$key+1] . '"';
        } elseif (false !== ($key = array_search('--file', $args))) { 
            $commit_args = '--file ' . $args[$key+1];
        } else { 
            $cli->error("No commit message defined.  Please define a commit message using either the -m or --file options.");
            return null;
        }

        // Get current branch
        $branch = null;
        exec("git branch", $lines);
        foreach ($lines as $line) { 
            if (!preg_match("/^\*(\s+?)(.+)$/", $line, $m)) { 
                continue;
            }
            $branch = trim($m[2]);
        }

        // Check branch
        if ($branch === null) { 
            $cli->error("Unable to determine current git branch");
            return null;
        }

        // Get remote
        $remote = null;
        exec("git remote", $rlines);
        if (count($rlines) == 0) { 
            $cli->error("No remote endpoints are setup on this git repository.  Please first do so via the 'git remote add <ALIAS> <URL>' command.");
            return null;
        } elseif (count($rlines) == 1) { 
            $remote = trim($rlines[0]);
        } else { 

            // Create options
            list($x, $options) = [1, []];
            foreach ($rlines as $line) { 
                $options[(string) $x] = trim($rline);
            $x++; }

            // Get option
            $opt = $cli->getOption("Select remote to publish to:", $options);
            $remote = $options[(string) $opt];
        }

        // Return
        return [$remote, $branch, $commit_args];
    }

    /**
     * Help
     */
    public function help():CliHelpScreen
    {

        $help = new CliHelpScreen(
            title: 'Sign and Release',
            usage: 'signer release [<VERSION>] [<PASSWORD>] [-m <MESSAGE>] [--file <COMMIT_FILE>]',
            description: 'Generates new digital signature, plus will commit, push, and tag the new release to the git repository all in the same command.',
        );

        // Params
        $help->addParam('version', 'Version number of the release.  This must be exactly the same as the version the git repo will be tagged with.  If unspecified, you will be prompted for the version.');
        $help->addParam('password', 'Optional private key / signing password surpressing any password prompts.');
        $help->addFlag('-m', 'Optional commit message.');
        $help->addFlag('--file', 'Optional location of a file containing the commit message.');

        // Add examples
        $help->addExample('./vendor/bin/signer release 2.4.1 -m "Update to v2.4.1"');
        //$help->addExample('./vendor/bin/signer release --file commit.txt');

        // Return
        return $help;
    }

}


