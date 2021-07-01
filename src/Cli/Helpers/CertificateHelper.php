<?php
declare(strict_types = 1);

namespace Apex\Signer\Cli\Helpers;

use Apex\Signer\Cli\Cli;
use Apex\Signer\Cli\Helpers\{DistinguishedNamesHelper, RsaKeysHelper};
use Apex\Signer\Stores\RsaKeyStore;
use Apex\Signer\Models\Certificate;

/**
 * Certificate helper
 */
class CertificateHelper
{

    /**
     * Generate CSR
     */
    public function generate(string $username, bool $is_apex = false):?Certificate
    {

        // Initialize
        $cli = new Cli();

        // Get distinguished name
        $dn_helper = new DistinguishedNamesHelper();
        do { 
            $dn = $dn_helper->create($cli, $username, $is_apex);
            if ($dn !== null) { 
                break;
            }
        } while (true);

        // Get RSA key
        $rsa_helper = new RsaKeysHelper();
        $rsa = $rsa_helper->get($username, $is_apex);
        $rsa->setAlias($username);

        // Generate CSR
        $privkey = $rsa->getPrivkey();
        $csr = openssl_csr_new($dn->toArray(), $privkey, ['digest_alg' => 'sha384']);
        openssl_csr_export($csr, $csr_out);

        // Self sign certificate, if needed
        $crt_out = '';
        if ($is_apex === false) { 
            $crt = openssl_csr_sign($csr, null, $privkey, 0, ['digest_alg' => 'sha384']);
            openssl_x509_export($crt, $crt_out);
        }

        // Get new certificate
        $cert = new Certificate(
            csr: $csr_out, 
            crt: $crt_out,
            rsa_key: $rsa, 
            dn: $dn
        );

        // Save
        $rsa_store = new RsaKeyStore($cli);
        $rsa_store->save($cert);

        // Return
        return $cert;
    }

}

