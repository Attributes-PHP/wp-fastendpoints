<?php

use Attributes\Wp\FastEndpoints\Tests\TestCase;

$composer = __DIR__.'/../vendor/autoload.php';
require_once $composer;

uses()->group('integration')->in('Integration');

uses(TestCase::class)->in('Unit');
