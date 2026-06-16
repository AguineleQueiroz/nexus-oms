<?php

use App\Database\Connection;

beforeEach(function () {
    $prop = (new ReflectionClass(Connection::class))->getProperty('instance');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
});

it('getInstance returns a PDO instance', function () {
    expect(Connection::getInstance())->toBeInstanceOf(PDO::class);
});

it('returns the same instance on consecutive calls', function () {
    expect(Connection::getInstance())->toBe(Connection::getInstance());
});

it('throws RuntimeException with wrong credentials', function () {
    $saved = array_intersect_key($_ENV, array_flip(['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD']));

    $_ENV['DB_HOST'] = '127.0.0.1';
    $_ENV['DB_PORT'] = '9999';
    $_ENV['DB_DATABASE'] = 'no_such_db';
    $_ENV['DB_USERNAME'] = 'nobody';
    $_ENV['DB_PASSWORD'] = 'bad';

    try {
        expect(fn() => Connection::getInstance())->toThrow(RuntimeException::class);
    } finally {
        foreach ($saved as $k => $v) {
            $_ENV[$k] = $v;
        }
    }
});
