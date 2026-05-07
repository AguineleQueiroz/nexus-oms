<?php

namespace Tests;

use Dotenv\Dotenv;

class TestCase
{
    public static function setUpBeforeClass(): void
    {
        $envFile = file_exists(__DIR__ . '/../.env.testing') ? '.env.testing' : '.env';
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..', $envFile);
        $dotenv->safeLoad();
    }
}
