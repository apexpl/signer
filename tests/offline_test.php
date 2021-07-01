<?php
declare(strict_types = 1);

namespace Apex\Signer\Tests;

use Apex\Signer\Cli\Cli;
use PHPUnit\Framework\TestCase;


/**
 * Aliases test
 */
class offline_test extends TestCase
{

    /**
     * Offline
     */
    public function test_ffline_new()
    {

        // Reset
        $cli = new Cli();
        $cli->reset();

        // Set args for init
        $args = [
            '2', 
            'CA', 
            'Ontario',
            'Toronto',
            'Test Man',
            'Test Unit',
            'test@apexpl.io',
            '',
            'testman12345',
            'testman12345'
        ];

        // Run init command
        $res = $cli->run(['init'], $args);
        $this->assertStringContainsString('Signer Successfully Initialized', $res);
        $this->assertStringContainsString('Please make a backup', $res);

        // Check key file
        $key_file = SITE_PATH . '/' . $cli->username . '.pem';
        $this->assertFileExists($key_file);
        $this->assertFileExists(SITE_PATH . '/signatures.json');

        // Certificate check
        $crt_file = $cli->confdir . '/certs/' . $cli->username . '.crt';
        $this->assertFileExists($crt_file);
        $info = openssl_x509_parse(file_get_contents($crt_file));
        $this->assertEquals($cli->username . '@packagist', $info['subject']['CN']);


    }

    /**
     * Sign
     */
    public function test_sign()
    {

        $cli = new Cli();
        $res = $cli->run(['sign'], ['1.2', 'testman12345']);
        $this->assertStringContainsString('Successfully signed new release for this package:', $res);

        // Check signature
        $json = json_decode(file_get_contents(SITE_PATH . '/signatures.json'), true);
        $this->assertIsArray($json);
        $rel = $json['releases']['1.2'] ?? [];
        $this->assertArrayHasKey('merkle_root', $rel);
        $this->assertArrayHasKey('signature', $rel);

        // Verify
        $crt = file_get_contents($cli->confdir . '/certs/' . $cli->username . '.crt');
        $ok = openssl_verify($rel['merkle_root'], hex2bin($rel['signature']), $crt, 'sha384');
        $this->assertEquals(1, $ok);
    }

    /**
     * Verify
     */
    public function test_verify()
    {

        $cli = new Cli();
        $res = $cli->run(['verify']);
        $this->assertStringContainsString('Verification Result', $res);
        $this->assertStringContainsString('apex/signer', $res);

    }


}


