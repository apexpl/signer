<?php
declare(strict_types = 1);

namespace Apex\Signer\Models;

/**
 * Distinguished name
 */
class DistinguishedName
{

    /**
     * Constructor
     */
    public function __construct(
        public string $country = '', 
        public string $province = '', 
        public string $locality = '', 
        public string $org_name = '', 
        public string $org_unit = '', 
        public string $common_name = '', 
        public string $email = '', 
        public string $usage = 'DigitalSignature'
    ) {

    }

    /**
     * toArray
     */
    public function toArray():array
    {


        $dn = [
            'countryName' => $this->country, 
            'stateOrProvinceName' => $this->province, 
            'localityName' => $this->locality, 
            'organizationName' => $this->org_name, 
            'organizationalUnitName' => $this->org_unit, 
            'commonName' => $this->common_name, 
            'emailAddress' => $this->email, 
            'keyUsage' => $this->usage 
        ];

        // Return
        return $dn;
    }

}


