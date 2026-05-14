<?php

/**
 * Seeder de pedidos para popular o dashboard com dados realistas.
 *
 * Modos:
 *   Histórico (padrão) — insere direto no banco, sem RabbitMQ. Rápido para grandes volumes.
 *   Live (--live)      — cria via OrderService + RabbitMQ. Workers processam em tempo real.
 *
 * Uso:
 *   php bin/seed.php
 *   php bin/seed.php --orders=200
 *   php bin/seed.php --orders=100 --clear
 *   php bin/seed.php --orders=500 --live
 *   php bin/seed.php --orders=500 --live --clear
 *
 * Via docker:
 *   docker compose exec api php bin/seed.php --orders=100 --live
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
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
$opts        = getopt('', ['orders::', 'clear', 'live']);
$totalOrders = (int) ($opts['orders'] ?? 50);
$clear       = isset($opts['clear']);
$live        = isset($opts['live']);

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
$redis->connect($_ENV['REDIS_HOST'] ?? 'localhost', (int) ($_ENV['REDIS_PORT'] ?? 6379));

/**
 * Fake data
 */
$customers = [
    ['João Silva',      'joao.silva@exemplo.com'],
    ['Maria Oliveira',  'maria.oliveira@exemplo.com'],
    ['Carlos Souza',    'carlos.souza@exemplo.com'],
    ['Ana Lima',        'ana.lima@exemplo.com'],
    ['Pedro Alves',     'pedro.alves@exemplo.com'],
    ['Fernanda Costa',  'fernanda.costa@exemplo.com'],
    ['Lucas Mendes',    'lucas.mendes@exemplo.com'],
    ['Juliana Rocha',   'juliana.rocha@exemplo.com'],
    ['Rafael Carvalho', 'rafael.carvalho@exemplo.com'],
    ['Beatriz Nunes',   'beatriz.nunes@exemplo.com'],
    ['Diego Martins',   'diego.martins@exemplo.com'],
    ['Camila Ferreira', 'camila.ferreira@exemplo.com'],
    ['Thiago Barbosa',  'thiago.barbosa@exemplo.com'],
    ['Larissa Gomes',   'larissa.gomes@exemplo.com'],
    ['Rodrigo Pinto',   'rodrigo.pinto@exemplo.com'],
    ['Amanda Ribeiro',  'amanda.ribeiro@exemplo.com'],
    ['Felipe Araújo',   'felipe.araujo@exemplo.com'],
    ['Vanessa Correia', 'vanessa.correia@exemplo.com'],
    ['Gustavo Lima',    'gustavo.lima@exemplo.com'],
    ['Priscila Torres', 'priscila.torres@exemplo.com'],
];

$products = [
    ['Tênis Nike Air Max',      45990],
    ['Meia Esportiva 3-pack',   2990],
    ['Camiseta Dry-fit',        7990],
    ['Shorts Academia',         5990],
    ['Garrafa Térmica 1L',      8990],
    ['Fone Bluetooth JBL',      34990],
    ['Mouse Gamer RGB',         19990],
    ['Teclado Mecânico',        28990],
    ['Monitor 27" Full HD',     129990],
    ['Headset USB',             12990],
    ['Mochila 30L',             18990],
    ['Livro Clean Code',        8990],
    ['Agenda Executiva',        4990],
    ['Caneta Montblanc',        24990],
    ['Cadeira de Escritório',   89990],
];


/**
 * conversion funil
 */
$statusDistribution = [
    'delivered'       => 40,
    'shipped'         => 20,
    'picking'         => 10,
    'paid'            => 10,
    'payment_pending' => 8,
    'cancelled'       => 7,
    'payment_refused' => 5,
];


/**
 * group by final status
 */
$eventChains = [
    'payment_pending' => [
        'order.created',
        'order.payment.pending',
    ],
    'paid' => [
        'order.created',
        'order.payment.pending',
        'order.payment.approved',
    ],
    'picking' => [
        'order.created',
        'order.payment.pending',
        'order.payment.approved',
        'order.picking',
    ],
    'shipped' => [
        'order.created',
        'order.payment.pending',
        'order.payment.approved',
        'order.picking',
        'order.shipped',
    ],
    'delivered' => [
        'order.created',
        'order.payment.pending',
        'order.payment.approved',
        'order.picking',
        'order.shipped',
        'order.delivered',
    ],
    'payment_refused' => [
        'order.created',
        'order.payment.pending',
        'order.payment.refused',
    ],
    'cancelled' => [
        'order.created',
        'order.payment.pending',
        'order.payment.approved',
        'order.cancelled',
    ],
];

$workers = [
    'PaymentWorker'      => 'orders.payment',
    'AuditWorker'        => 'orders.audit',
    'NotificationWorker' => 'orders.notification',
    'FulfillmentWorker'  => 'orders.fulfillment',
    'InventoryWorker'    => 'orders.inventory',
    'TrackingWorker'     => 'orders.tracking',
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

function randomTimestamp(int $hoursBack = 48): string
{
    /**
     * Grouping during peak hours: 9am–12pm and 2pm–6pm
     */
    $now  = time();
    $base = $now - mt_rand(0, $hoursBack * 3600);

    /**
     * 60% chance of it crashing during peak hours.
     */
    if (mt_rand(1, 10) <= 6) {
        $hour = mt_rand(0, 1) === 0 ? mt_rand(9, 11) : mt_rand(14, 17);
        $base = mktime($hour, mt_rand(0, 59), mt_rand(0, 59), (int) date('n', $base), (int) date('j', $base), (int) date('Y', $base));
    }

    return date('Y-m-d H:i:s', $base);
}

function progressTimestamp(string $base, int $stepSeconds): string
{
    return date('Y-m-d H:i:s', strtotime($base) + $stepSeconds);
}

function progressBar(int $current, int $total, int $width = 20): string
{
    $filled = (int) round($current / max($total, 1) * $width);
    return '[' . str_repeat('█', $filled) . str_repeat('░', $width - $filled) . ']';
}

function formatEta(int $seconds): string
{
    if ($seconds < 60)  return "{$seconds}s";
    if ($seconds < 3600) return floor($seconds / 60) . 'm' . ($seconds % 60) . 's';
    return floor($seconds / 3600) . 'h' . floor(($seconds % 3600) / 60) . 'm';
}

function randomItems(array $products): array
{
    $items = [];
    for ($j = 0, $c = mt_rand(1, 4); $j < $c; $j++) {
        $product = $products[array_rand($products)];
        $qty     = mt_rand(1, 3);
        $items[] = ['product' => $product[0], 'qty' => $qty, 'price' => $product[1]];
    }
    return $items;
}

echo "\nNexus OMS — Seeder" . ($live ? ' [LIVE]' : '') . "\n";
echo str_repeat('─', 40) . "\n";

if ($clear) {
    echo "Clearing existing data... ";
    $pdo->exec('TRUNCATE order_events, processed_events, orders RESTART IDENTITY CASCADE');
    $keys = $redis->keys('order:*');
    if ($keys) {
        $redis->del($keys);
    }
    echo "done\n";
}

/**
 * Live mode
 */
if ($live) {
    echo "Conectando ao RabbitMQ... ";
    try {
        $amqp    = new AMQPStreamConnection(
            $_ENV['RABBITMQ_HOST']     ?? 'rabbitmq',
            (int) ($_ENV['RABBITMQ_PORT']     ?? 5672),
            $_ENV['RABBITMQ_USER']     ?? 'guest',
            $_ENV['RABBITMQ_PASSWORD'] ?? 'guest',
        );
        $channel = $amqp->channel();
    } catch (\Throwable $e) {
        echo "ERRO: " . $e->getMessage() . "\n";
        exit(1);
    }
    echo "ok\n";

    $publisher    = new EventPublisher($channel);
    $publisher->setupExchangesAndQueues();

    $orderRepo    = new OrderRepository(Connection::getInstance());
    $eventRepo    = new EventRepository(Connection::getInstance());
    $orderService = new OrderService($orderRepo, $eventRepo, $publisher);

    echo "Criando {$totalOrders} pedidos via RabbitMQ...\n";
    echo "(Workers processarão em segundo plano)\n\n";

    $created = 0;
    $failed  = 0;
    $start   = microtime(true);

    for ($i = 0; $i < $totalOrders; $i++) {
        $customer = $customers[array_rand($customers)];
        $items    = randomItems($products);
        $total    = array_sum(array_map(fn($it) => $it['price'] * $it['qty'], $items));

        try {
            $orderService->createOrder([
                'customer_name'   => $customer[0],
                'customer_email'  => $customer[1],
                'items'           => $items,
                'total'           => $total,
                'idempotency_key' => 'live-' . uuid(),
            ]);
            $created++;
        } catch (\Throwable) {
            $failed++;
        }

        $elapsed = microtime(true) - $start;
        $done    = $i + 1;
        $rate    = $done / max($elapsed, 0.001);
        $etaSec  = (int) (($totalOrders - $done) / max($rate, 0.001));
        echo "\r  " . progressBar($done, $totalOrders) . " {$done}/{$totalOrders}  | " . number_format($rate, 1) . " ord/s | ETA " . formatEta($etaSec) . "   ";
    }

    $elapsed = microtime(true) - $start;

    echo "\n\n";
    echo "  Pedidos criados:  {$created}\n";
    if ($failed > 0) {
        echo "  Falhas:           {$failed}\n";
    }
    echo "  Tempo total:      " . number_format($elapsed, 1) . "s\n";
    echo "  Taxa média:       " . number_format($created / max($elapsed, 0.001), 1) . " ord/s\n";
    echo "\nDone ✓\n\n";

    $channel->close();
    $amqp->close();
    exit(0);
}


/**
 * Historical mode (default)
 */
$statusList = [];
$remaining  = $totalOrders;

foreach ($statusDistribution as $status => $pct) {
    $count = (int) round($totalOrders * $pct / 100);
    for ($i = 0; $i < $count && $remaining > 0; $i++) {
        $statusList[] = $status;
        $remaining--;
    }
}

while ($remaining > 0) {
    $statusList[] = 'delivered';
    $remaining--;
}

shuffle($statusList);

echo "Seeding {$totalOrders} orders (histórico)...\n";

$insertOrder = $pdo->prepare('
    INSERT INTO orders (id, customer_name, customer_email, items, total, status, idempotency_key, metadata, created_at, updated_at)
    VALUES (:id, :customer_name, :customer_email, :items, :total, :status, :idempotency_key, :metadata, :created_at, :updated_at)
');

$insertEvent = $pdo->prepare('
    INSERT INTO order_events (id, order_id, event_type, routing_key, payload, worker_id, processed, processed_at, published_at)
    VALUES (:id, :order_id, :event_type, :routing_key, :payload, :worker_id, :processed, :processed_at, :published_at)
');

$totalEvents = 0;
$summary     = array_fill_keys(array_keys($statusDistribution), 0);

foreach ($statusList as $i => $finalStatus) {
    $customer  = $customers[array_rand($customers)];
    $orderId   = uuid();
    $createdAt = randomTimestamp(48);
    $items     = randomItems($products);
    $total     = array_sum(array_map(fn($it) => $it['price'] * $it['qty'], $items));

    $metadata = [];
    if ($finalStatus === 'shipped' || $finalStatus === 'delivered') {
        $metadata['tracking_code'] = 'BR' . str_pad((string) mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
    }

    $insertOrder->execute([
        ':id'              => $orderId,
        ':customer_name'   => $customer[0],
        ':customer_email'  => $customer[1],
        ':items'           => json_encode($items),
        ':total'           => $total,
        ':status'          => $finalStatus,
        ':idempotency_key' => 'seed-' . $orderId,
        ':metadata'        => json_encode($metadata),
        ':created_at'      => $createdAt,
        ':updated_at'      => $createdAt,
    ]);

    $chain     = $eventChains[$finalStatus] ?? ['order.created'];
    $stepDelay = 0;

    foreach ($chain as $eventType) {
        $stepDelay  += mt_rand(2, 120);
        $publishedAt = progressTimestamp($createdAt, $stepDelay);
        $processedAt = progressTimestamp($publishedAt, mt_rand(1, 10));
        $workerId    = match (true) {
            str_contains($eventType, 'payment')  => 'payment-worker-1',
            $eventType === 'order.picking'        => 'fulfillment-worker-1',
            $eventType === 'order.shipped'        => 'tracking-worker-1',
            default                               => 'audit-worker-1',
        };

        $insertEvent->execute([
            ':id'           => uuid(),
            ':order_id'     => $orderId,
            ':event_type'   => $eventType,
            ':routing_key'  => $eventType,
            ':payload'      => json_encode([
                'customer_name'  => $customer[0],
                'customer_email' => $customer[1],
                'total'          => $total,
                'items'          => $items,
                'status'         => $finalStatus,
            ]),
            ':worker_id'    => $workerId,
            ':processed'    => 'true',
            ':processed_at' => $processedAt,
            ':published_at' => $publishedAt,
        ]);

        $totalEvents++;
    }

    $redis->set("order:snapshot:{$orderId}", json_encode([
        'id'             => $orderId,
        'customer_name'  => $customer[0],
        'customer_email' => $customer[1],
        'total'          => $total,
        'status'         => $finalStatus,
        'items'          => $items,
        'metadata'       => $metadata,
        'created_at'     => $createdAt,
    ]));

    $summary[$finalStatus] = ($summary[$finalStatus] ?? 0) + 1;

    echo "\r  " . progressBar($i + 1, $totalOrders) . " " . ($i + 1) . "/{$totalOrders}  ";
}

echo "\n\n";

$heartbeatInterval = (int) ($_ENV['HEARTBEAT_INTERVAL'] ?? 5);
foreach ($workers as $workerType => $queue) {
    $workerId = strtolower(preg_replace('/Worker$/', '', $workerType)) . '-worker-1';
    $redis->setex("worker:heartbeat:{$workerId}", $heartbeatInterval * 3 * 60, json_encode([
        'worker_id'        => $workerId,
        'worker_type'      => $workerType,
        'queue_name'       => $queue,
        'status'           => 'active',
        'last_heartbeat'   => date('c'),
        'events_processed' => mt_rand(50, 500),
        'events_failed'    => mt_rand(0, 10),
        'started_at'       => date('c', strtotime('-1 hour')),
    ]));
}

echo "Summary:\n";
$maxLen = max(array_map('strlen', array_keys($summary)));
arsort($summary);
foreach ($summary as $status => $count) {
    echo sprintf("  %-{$maxLen}s  %d orders\n", $status, $count);
}

echo "\n";
echo "  Total events inserted:    {$totalEvents}\n";
echo "  Redis snapshots updated:  {$totalOrders}\n";
echo "  Worker heartbeats:        " . count($workers) . "\n";
echo "\nDone ✓\n\n";
