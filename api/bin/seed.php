<?php

/**
 * Seeder de pedidos para popular o dashboard com dados realistas.
 *
 * Cria pedidos via OrderService + RabbitMQ. Workers processam em tempo real.
 * 20% dos pedidos já nascem em 'shipped' para o TrackingWorker processar.
 * Use --no-shipped para desativar esse comportamento.
 *
 * Uso:
 *   php bin/seed.php
 *   php bin/seed.php --orders=200
 *   php bin/seed.php --orders=100 --clear
 *   php bin/seed.php --orders=500 --no-shipped
 *   php bin/seed.php --orders=500 --clear --no-shipped
 *
 * Via docker:
 *   docker compose exec api php bin/seed.php --orders=100
 *   docker compose exec api php bin/seed.php --orders=500 --clear
 *   docker compose exec api php bin/seed.php --orders=500 --no-shipped
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
use App\Events\OrderEvent;
use App\Repositories\EventRepository;
use App\Repositories\OrderRepository;
use App\Services\EventPublisher;
use App\Services\OrderService;
use Dotenv\Dotenv;
use PhpAmqpLib\Connection\AMQPStreamConnection;

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

/**
 * CLI args
 */
$opts = getopt('', ['orders::', 'clear', 'no-shipped']);
$totalOrders = (int)($opts['orders'] ?? 50);
$clear = isset($opts['clear']);
$shipped = !isset($opts['no-shipped']);

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

/**
 * Fake data
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
    ['Diego Martins', 'diego.martins@exemplo.com'],
    ['Camila Ferreira', 'camila.ferreira@exemplo.com'],
    ['Thiago Barbosa', 'thiago.barbosa@exemplo.com'],
    ['Larissa Gomes', 'larissa.gomes@exemplo.com'],
    ['Rodrigo Pinto', 'rodrigo.pinto@exemplo.com'],
    ['Amanda Ribeiro', 'amanda.ribeiro@exemplo.com'],
    ['Felipe Araújo', 'felipe.araujo@exemplo.com'],
    ['Vanessa Correia', 'vanessa.correia@exemplo.com'],
    ['Gustavo Lima', 'gustavo.lima@exemplo.com'],
    ['Priscila Torres', 'priscila.torres@exemplo.com'],
];

$products = [
    ['Tênis Nike Air Max', 45990],
    ['Meia Esportiva 3-pack', 2990],
    ['Camiseta Dry-fit', 7990],
    ['Shorts Academia', 5990],
    ['Garrafa Térmica 1L', 8990],
    ['Fone Bluetooth JBL', 34990],
    ['Mouse Gamer RGB', 19990],
    ['Teclado Mecânico', 28990],
    ['Monitor 27" Full HD', 129990],
    ['Headset USB', 12990],
    ['Mochila 30L', 18990],
    ['Livro Clean Code', 8990],
    ['Agenda Executiva', 4990],
    ['Caneta Montblanc', 24990],
    ['Cadeira de Escritório', 89990],
];

/**
 * helpers
 */
function uuid(): string
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function progressBar(int $current, int $total, int $width = 20): string
{
    $filled = (int)round($current / max($total, 1) * $width);
    return '[' . str_repeat('█', $filled) . str_repeat('░', $width - $filled) . ']';
}

function formatEta(int $seconds): string
{
    if ($seconds < 60) return "{$seconds}s";
    if ($seconds < 3600) return floor($seconds / 60) . 'm' . ($seconds % 60) . 's';
    return floor($seconds / 3600) . 'h' . floor(($seconds % 3600) / 60) . 'm';
}

const C_GREEN = "\033[32m";
const C_RED = "\033[31m";
const C_YELLOW = "\033[33m";
const C_BOLD = "\033[1m";
const C_RESET = "\033[0m";
const C_DIM = "\033[2m";

function randomItems(array $products): array
{
    $items = [];
    for ($j = 0, $c = mt_rand(1, 4); $j < $c; $j++) {
        $product = $products[array_rand($products)];
        $qty = mt_rand(1, 3);
        $items[] = ['product' => $product[0], 'qty' => $qty, 'price' => $product[1]];
    }
    return $items;
}

echo "\nNexus OMS — Seeder\n";
echo str_repeat('─', 40) . "\n";

if ($clear) {
    echo "Clearing existing data... ";
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

echo "Conectando ao RabbitMQ... ";
try {
    $amqp = new AMQPStreamConnection(
        $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq',
        (int)($_ENV['RABBITMQ_PORT'] ?? 5672),
        $_ENV['RABBITMQ_USER'] ?? 'guest',
        $_ENV['RABBITMQ_PASSWORD'] ?? 'guest',
    );
    $channel = $amqp->channel();
} catch (\Throwable $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
echo "ok\n";

$publisher = new EventPublisher($channel);
$publisher->setupExchangesAndQueues();

$orderRepo = new OrderRepository(Connection::getInstance());
$eventRepo = new EventRepository(Connection::getInstance());
$orderService = new OrderService($orderRepo, $eventRepo, $publisher);

$shippedCount = $shipped ? (int)round($totalOrders * 0.20) : 0;
$normalCount = $totalOrders - $shippedCount;

$shippedLabel = $shippedCount > 0 ? " ({$shippedCount} shipped para TrackingWorker)" : '';
echo "Criando {$totalOrders} pedidos via RabbitMQ{$shippedLabel}...\n";
echo "(Workers processarão em segundo plano)\n\n";

$created = 0;
$failed = 0;
$start = microtime(true);

$insertShippedOrder = $pdo->prepare('
    INSERT INTO orders (id, customer_name, customer_email, items, total, status, idempotency_key, metadata)
    VALUES (:id, :customer_name, :customer_email, :items, :total, :status, :idempotency_key, :metadata)
');

// Shipped orders: direct DB insert + publish order.shipped for TrackingWorker
for ($i = 0; $i < $shippedCount; $i++) {
    $customer = $customers[array_rand($customers)];
    $items = randomItems($products);
    $total = array_sum(array_map(fn($it) => $it['price'] * $it['qty'], $items));
    $orderId = uuid();

    $insertShippedOrder->execute([
        ':id' => $orderId,
        ':customer_name' => $customer[0],
        ':customer_email' => $customer[1],
        ':items' => json_encode($items),
        ':total' => $total,
        ':status' => 'shipped',
        ':idempotency_key' => 'live-shipped-' . $orderId,
        ':metadata' => json_encode([]),
    ]);

    $event = OrderEvent::create('order.shipped', $orderId, [
        'customer_name' => $customer[0],
        'customer_email' => $customer[1],
        'total' => $total,
        'status' => 'shipped',
    ]);
    $eventRepo->save($event, 'order.shipped');
    $publisher->publish($event);

    $created++;
    $done = $i + 1;
    $elapsed = microtime(true) - $start;
    $rate = $done / max($elapsed, 0.001);
    $etaSec = (int)(($totalOrders - $done) / max($rate, 0.001));
    echo "\r  " . progressBar($done, $totalOrders) . " {$done}/{$totalOrders}  | " . number_format($rate, 1) . " ord/s | ETA " . formatEta($etaSec) . "   ";
}

// Normal orders via OrderService + RabbitMQ
for ($i = 0; $i < $normalCount; $i++) {
    $customer = $customers[array_rand($customers)];
    $items = randomItems($products);
    $total = array_sum(array_map(fn($it) => $it['price'] * $it['qty'], $items));

    try {
        $orderService->createOrder([
            'customer_name' => $customer[0],
            'customer_email' => $customer[1],
            'items' => $items,
            'total' => $total,
            'idempotency_key' => 'live-' . uuid(),
        ]);
        $created++;
    } catch (\Throwable) {
        $failed++;
    }

    $elapsed = microtime(true) - $start;
    $done = $shippedCount + $i + 1;
    $rate = $done / max($elapsed, 0.001);
    $etaSec = (int)(($totalOrders - $done) / max($rate, 0.001));
    echo "\r  " . progressBar($done, $totalOrders) . " {$done}/{$totalOrders}  | " . number_format($rate, 1) . " ord/s | ETA " . formatEta($etaSec) . "   ";
}

$elapsed = microtime(true) - $start;

echo "\n\n";
echo "  Pedidos criados:  {$created}\n";
if ($shippedCount > 0) {
    echo "  Shipped (tracking): {$shippedCount}\n";
}
if ($failed > 0) {
    echo "  Falhas:           {$failed}\n";
}
echo "  Tempo total:      " . number_format($elapsed, 1) . "s\n";
echo "  Taxa média:       " . number_format($created / max($elapsed, 0.001), 1) . " ord/s\n";

$channel->close();
$amqp->close();

/**
 * Monitor loop
 */
$stmtEvents = $pdo->prepare('
    SELECT id, order_id, event_type, worker_id, processed, error, published_at, processed_at
    FROM order_events
    ORDER BY published_at DESC
    LIMIT 25
');

$sep = str_repeat('─', 80);

echo "\nMonitorando processamento... (Ctrl+C para sair)\n";

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
    echo C_BOLD . "Nexus OMS — Seed" . C_RESET . "  " . C_DIM . "● {$now}" . C_RESET . "\n";
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

    echo "\n" . C_DIM . "  auto-refresh 2s — Ctrl+C para sair" . C_RESET . "\n";

    sleep(2);
}
