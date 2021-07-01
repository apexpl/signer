<?php
declare(strict_types = 1);

namespace Apex\Signer\Cli\Helpers;

use Apex\Signer\Cli\Cli;
use Apex\Signer\Models\DistinguishedName;

/**
 * Key generator
 */
class DistinguishedNamesHelper
{

    /**
     * Create distinguished name
     */
    public function create(Cli $cli, string $username, bool $is_apex = false):?DistinguishedName
    {

        // Send header
        $cli->sendHeader("Signing Certificate");
        $cli->send("A new signing certificate will now be generated.  Please answer the below questions as desired.  This information does not necessarily have to be legitimate,, but is how you will be identified throughout the network.\r\n\r\n");
        $cli->send("NOTE: Don't worry, you will only need to do this once, and never again.\r\n\r\n");

        // Get input fields
        $country = $cli->getInput('Country Code [AU]: ', 'AU');
        $province = $cli->getInput('Province / State Name: ');
        $locality = $cli->getInput('City / Locality Name: ');
        $org_name = $cli->getInput('Organization / Full Name []: ');
        $org_unit = $cli->getInput('Organization Unit [Dev Team]: ', 'Dev Team');
        $email = $cli->getInput('E-Mail Address []: ');

        // Send details for review
        $cli->send("\r\nThe below details will be included in all releases you publish:\r\n\r\n");
        $cli->send("    $org_name ($org_unit)\r\n");
        $cli->send("    $locality, $province, $country\r\n");
        $cli->send("    $email\r\n\r\n");

        // Confirm details
        if ($cli->getConfirm('Is this correct?') !== true) { 
            $cli->send("Ok, it's not correct.  Regenerating details...\r\n\r\n");
            return null;
        }
        $cli->send("\r\n");

        // Get DN
        $dn = new DistinguishedName(
            country: $country, 
            province: $province, 
            locality: $locality, 
            org_name: $org_name, 
            org_unit: $org_unit, 
            email: $email,
            common_name: $is_apex === true ? $username . '@apex' : $username . '@packagist'
        );

        // Return
        return $dn;
    }

}


