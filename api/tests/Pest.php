<?php

use Dotenv\Dotenv;

$envFile = file_exists(__DIR__ . '/../.env.testing') ? '.env.testing' : '.env';
Dotenv::createUnsafeImmutable(__DIR__ . '/..', $envFile)->safeLoad();

uses(Tests\TestCase::class)->in('tests');
