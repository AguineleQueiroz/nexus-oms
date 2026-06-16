<?php

/**
 * Real-time event stream monitor. Polls order_events and renders a live table.
 * With --orders=N, injects N orders into the pipeline via RabbitMQ before monitoring.
 *
 * Uso:
 *   php bin/watch.php
 *   php bin/watch.php --orders=30
 *   php bin/watch.php --orders=50 --clear
 *
 * Via docker:
 *   docker compose exec api php bin/watch.php --orders=30
 *   docker compose exec api php bin/watch.php --orders=50 --clear
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
use App\Repositories\OrderRepository;
use App\Services\EventPublisher;
use App\Services\OrderService;
use Dotenv\Dotenv;
use PhpAmqpLib\Connection\AMQPStreamConnection;

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();


/**
 * CLI arguments
 */
$opts = getopt('', ['clear', 'orders::', 'interval::']);
$clear = isset($opts['clear']);
$totalOrders = (int)($opts['orders'] ?? 0);
$interval = max(1, (int)($opts['interval'] ?? 2));


/**
 * Fake data - mirrors seed.php
 */
$customers = [
    ['João Silva', 'joao.silva@exemplo.com'],
    ['Maria Oliveira', 'maria.oliveira@exemplo.com'],
    ['Carlos Souza', 'carlos.souza@exemplo.com'],
    ['Ana Lima', 'ana.lima@exemplo.com'],
    ['Pedro Alves', 'pedro.alves@exemplo.com'],
    ['Fernanda Costa', 'fernanda.costa@exemplo.com'],
    ['Lucas Mendes', 'lucas.mendes@exemplo.com'],
    ['Juliana Rocha', 'juliana.rocha@exemplo.com'],
    ['Rafael Carvalho', 'rafael.carvalho@exemplo.com'],
    ['Beatriz Nunes', 'beatriz.nunes@exemplo.com'],
];

$products = [
    ['Tênis Nike Air Max', 45990],
    ['Fone Bluetooth JBL', 34990],
    ['Mouse Gamer RGB', 19990],
    ['Teclado Mecânico', 28990],
    ['Monitor 27" Full HD', 129990],
    ['Mochila 30L', 18990],
    ['Livro Clean Code', 8990],
    ['Cadeira de Escritório', 89990],
];

/**
 * Helpers function
 */
function watchUuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function watchItems(array $products): array
{
    $items = [];
    for ($j = 0, $c = mt_rand(1, 3); $j < $c; $j++) {
        $p = $products[array_rand($products)];
        $items[] = ['product' => $p[0], 'qty' => mt_rand(1, 2), 'price' => $p[1]];
    }
    return $items;
}

function progressBar(int $current, int $total, int $width = 20): string
{
    $filled = (int)round($current / max($total, 1) * $width);
    return '[' . str_repeat('█', $filled) . str_repeat('░', $width - $filled) . ']';
}


/**
 * ANSI codes
 */
const C_GREEN = "\033[32m";
const C_RED = "\033[31m";
const C_YELLOW = "\033[33m";
const C_CYAN = "\033[36m";
const C_BOLD = "\033[1m";
const C_RESET = "\033[0m";
const C_DIM = "\033[2m";

/**
 * Connections
 */
$pdo = new PDO(
    sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $_ENV['DB_HOST'] ?? 'localhost',
        $_ENV['DB_PORT'] ?? 5432,
        $_ENV['DB_DATABASE'] ?? 'oms',
    ),
    $_ENV['DB_USERNAME'] ?? 'user',
    $_ENV['DB_PASSWORD'] ?? 'secret',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
);

$redis = new Redis();
$redis->connect($_ENV['REDIS_HOST'] ?? 'localhost', (int)($_ENV['REDIS_PORT'] ?? 6379));


if ($clear) {
    echo "\nClearing existing data... ";
    $pdo->exec('TRUNCATE order_events, processed_events, orders RESTART IDENTITY CASCADE');
    $pdo->exec('TRUNCATE consumers_log RESTART IDENTITY CASCADE');
    foreach (['order:*', 'worker:heartbeat:*'] as $pattern) {
        $keys = $redis->keys($pattern);
        if ($keys) {
            $redis->del($keys);
        }
    }
    echo "done\n";
}


if ($totalOrders > 0) {
    echo "\nConectando ao RabbitMQ... ";
    try {
        $amqp = new AMQPStreamConnection(
            $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq',
            (int)($_ENV['RABBITMQ_PORT'] ?? 5672),
            $_ENV['RABBITMQ_USER'] ?? 'guest',
            $_ENV['RABBITMQ_PASSWORD'] ?? 'guest',
        );
        $channel = $amqp->channel();
    } catch (\Throwable $e) {
        echo "ERRO: " . $e->getMessage() . "\n\n";
        exit(1);
    }
    echo "ok\n";

    $publisher = new EventPublisher($channel);
    $publisher->setupExchangesAndQueues();

    $orderRepo = new OrderRepository(Connection::getInstance());
    $orderService = new OrderService($orderRepo, new \App\Repositories\EventRepository(Connection::getInstance()), $publisher);

    echo "Injetando {$totalOrders} pedidos...\n";

    $done = 0;
    $start = microtime(true);

    for ($i = 0; $i < $totalOrders; $i++) {
        $customer = $customers[array_rand($customers)];
        $items = watchItems($products);
        $total = array_sum(array_map(fn($it) => $it['price'] * $it['qty'], $items));

        try {
            $orderService->createOrder([
                'customer_name' => $customer[0],
                'customer_email' => $customer[1],
                'items' => $items,
                'total' => $total,
                'idempotency_key' => 'watch-live-' . watchUuid(),
            ]);
        } catch (\Throwable) {
        }

        $done++;
        $elapsed = microtime(true) - $start;
        echo "\r  " . progressBar($done, $totalOrders) . " {$done}/{$totalOrders}   ";
    }

    $elapsed = microtime(true) - $start;
    echo "\n  Injetados em " . number_format($elapsed, 1) . "s — monitorando processamento...\n";

    $channel->close();
    $amqp->close();
}


$stmtEvents = $pdo->prepare('
    SELECT id, order_id, event_type, worker_id, processed, error, published_at, processed_at
    FROM order_events
    ORDER BY published_at DESC
    LIMIT 25
');

$sep = str_repeat('─', 80);

echo "\n";

while (true) {
    $stmtEvents->execute();
    $events = $stmtEvents->fetchAll();

    $statsRow = $pdo->query("
        SELECT
            COUNT(*) FILTER (WHERE processed = TRUE)                        AS ok,
            COUNT(*) FILTER (WHERE processed = FALSE AND error IS NOT NULL) AS failed,
            COUNT(*) FILTER (WHERE processed = FALSE AND error IS NULL)     AS pending
        FROM order_events
        WHERE published_at > NOW() - INTERVAL '1 hour'
    ")->fetch();

    $workerRow = $pdo->query("
        SELECT COUNT(*) AS cnt
        FROM consumers_log
        WHERE last_heartbeat > NOW() - INTERVAL '1 minute'
    ")->fetch();

    echo "\033[2J\033[H";

    $now = date('H:i:s');
    echo C_BOLD . "Nexus OMS — Watch" . C_RESET . "  " . C_DIM . "● {$now}" . C_RESET . "\n";
    echo $sep . "\n";

    $wCnt = (int)$workerRow['cnt'];
    $ok = (int)$statsRow['ok'];
    $fail = (int)$statsRow['failed'];
    $pend = (int)$statsRow['pending'];
    echo C_BOLD . "Workers:" . C_RESET . " {$wCnt} active";
    echo "  |  Events (1h): ";
    echo C_GREEN . "{$ok} processed" . C_RESET;
    echo "  " . C_RED . "{$fail} failed" . C_RESET;
    if ($pend > 0) {
        echo "  " . C_YELLOW . "{$pend} pending" . C_RESET;
    }
    echo "\n\n";

    $fmt = "  %-9s  %-26s  %-10s  %-22s  %s\n";
    printf(C_DIM . $fmt . C_RESET, 'TIME', 'EVENT TYPE', 'ORDER', 'WORKER', 'ST');
    echo "  " . str_repeat('─', 76) . "\n";

    foreach ($events as $row) {
        $time = substr($row['published_at'] ?? '', 11, 8);
        $type = $row['event_type'] ?? '';
        $orderId = substr($row['order_id'] ?? '', 0, 8);
        $worker = $row['worker_id'] ?? '';
        $processed = (bool)($row['processed'] === true || $row['processed'] === 't' || $row['processed'] === '1');
        $hasError = !empty($row['error']);

        if ($processed) {
            $status = C_GREEN . '✓' . C_RESET;
            $color = C_RESET;
        } elseif ($hasError) {
            $shortErr = mb_substr($row['error'], 0, 28);
            $status = C_RED . '✗ ' . $shortErr . C_RESET;
            $color = C_DIM;
        } else {
            $status = C_YELLOW . '⋯' . C_RESET;
            $color = C_DIM;
        }

        echo "  ";
        echo $color . sprintf('%-9s  %-26s  %-10s  %-22s  ', $time, $type, $orderId, $worker) . C_RESET;
        echo $status . "\n";
    }

    echo "\n" . C_DIM . "  auto-refresh {$interval}s — Ctrl+C to stop" . C_RESET . "\n";

    sleep($interval);
}
