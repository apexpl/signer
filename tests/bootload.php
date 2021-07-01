<?php

// Composer
require_once(__DIR__ . '/../vendor/autoload.php');

// Set dir
define('SITE_PATH', realpath(__DIR__ . '/../'));

// Class aliases
class_alias(\Apex\Signer\Tests\Stubs\CliStub::class, \Apex\Signer\Cli\Cli::class);

