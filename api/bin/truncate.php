<?php

/**
 * Truncates all OMS tables and clears Redis keys.
 *
 * Usage:
 *   php bin/truncate.php          # asks for confirmation
 *   php bin/truncate.php --yes    # skips confirmation prompt
 *
 * Via docker:
 *   docker compose exec api php bin/truncate.php --yes
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

$opts = getopt('', ['yes']);
$skip = isset($opts['yes']);

echo "\nNexus OMS — Truncate\n";
echo str_repeat('─', 40) . "\n\n";

if (!$skip) {
    echo "  This will permanently delete ALL orders, events, and worker logs.\n\n";
    echo "  Type 'yes' to confirm: ";
    $input = trim(fgets(STDIN));
    echo "\n";
    if ($input !== 'yes') {
        echo "  Aborted.\n\n";
        exit(0);
    }
}

$pdo = new PDO(
    sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $_ENV['DB_HOST'] ?? 'localhost',
        $_ENV['DB_PORT'] ?? 5432,
        $_ENV['DB_DATABASE'] ?? 'oms',
    ),
    $_ENV['DB_USERNAME'] ?? 'user',
    $_ENV['DB_PASSWORD'] ?? 'secret',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$redis = new Redis();
$redis->connect($_ENV['REDIS_HOST'] ?? 'localhost', (int) ($_ENV['REDIS_PORT'] ?? 6379));

echo "  Truncating tables... ";
$pdo->exec('TRUNCATE order_events, processed_events, orders RESTART IDENTITY CASCADE');
$pdo->exec('TRUNCATE consumers_log RESTART IDENTITY CASCADE');
echo "done\n";

echo "  Clearing Redis...    ";
foreach (['order:*', 'worker:heartbeat:*'] as $pattern) {
    $keys = $redis->keys($pattern);
    if ($keys) {
        $redis->del($keys);
    }
}
echo "done\n\n";

echo "Done ✓\n\n";
