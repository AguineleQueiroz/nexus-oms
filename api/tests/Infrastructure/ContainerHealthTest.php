<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;

test('postgresql connects with env credentials', function () {
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT');
    $db = getenv('DB_DATABASE');
    $user = getenv('DB_USERNAME');
    $pass = getenv('DB_PASSWORD');

    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    expect($pdo->query('SELECT 1')->fetchColumn())->toEqual(1);
});

test('rabbitmq amqp connection succeeds', function () {
    $conn = new AMQPStreamConnection(
        getenv('RABBITMQ_HOST'),
        getenv('RABBITMQ_PORT'),
        getenv('RABBITMQ_USER'),
        getenv('RABBITMQ_PASSWORD'),
        getenv('RABBITMQ_VHOST'),
    );

    expect($conn->isConnected())->toBeTrue();

    $conn->close();
});

test('redis ping returns pong', function () {
    $redis = new Redis();
    $redis->connect(getenv('REDIS_HOST'), (int)getenv('REDIS_PORT'));

    expect($redis->ping())->toBeTrue();
});

test('all required env vars are present', function () {
    $required = [
        'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
        'RABBITMQ_HOST', 'RABBITMQ_PORT', 'RABBITMQ_USER', 'RABBITMQ_PASSWORD', 'RABBITMQ_VHOST',
        'REDIS_HOST', 'REDIS_PORT',
        'MAIL_HOST', 'MAIL_PORT', 'MAIL_FROM',
        'WORKER_TYPE', 'WORKER_ID', 'HEARTBEAT_INTERVAL', 'MAX_ATTEMPTS', 'RETRY_BASE_TTL',
    ];

    foreach ($required as $key) {
        expect(getenv($key))->not->toBeFalsy("Missing env var: $key");
    }
});
