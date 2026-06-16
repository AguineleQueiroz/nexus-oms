<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
use App\Mail\SmtpMailer;
use App\Repositories\EventRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ReadModelRepository;
use App\Services\EventPublisher;
use App\Services\OrderService;
use App\Workers\AuditWorker;
use App\Workers\FulfillmentWorker;
use App\Workers\InventoryWorker;
use App\Workers\NotificationWorker;
use App\Workers\PaymentWorker;
use App\Workers\TrackingWorker;
use Dotenv\Dotenv;
use PhpAmqpLib\Connection\AMQPStreamConnection;

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

$workerType = $_ENV['WORKER_TYPE'] ?? throw new \RuntimeException('WORKER_TYPE not set');
$workerId = $_ENV['WORKER_ID'] ?? throw new \RuntimeException('WORKER_ID not set');

$pdo = Connection::getInstance();

$redis = new Redis();
$redis->connect($_ENV['REDIS_HOST'] ?? 'redis', (int)($_ENV['REDIS_PORT'] ?? 6379));

$channel = null;
try {
    $amqp = new AMQPStreamConnection(
        $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq',
        (int)($_ENV['RABBITMQ_PORT'] ?? 5672),
        $_ENV['RABBITMQ_USER'] ?? 'guest',
        $_ENV['RABBITMQ_PASSWORD'] ?? 'guest',
    );
    $channel = $amqp->channel();
} catch (Exception $e) {
    echo 'Caught exception: ', $e->getMessage(), "\n";
}

$publisher = new EventPublisher($channel);
$publisher->setupExchangesAndQueues();

$orderRepo = new OrderRepository($pdo);
$eventRepo = new EventRepository($pdo);
$readModel = new ReadModelRepository($redis, $pdo);
$orderService = new OrderService($orderRepo, $eventRepo, $publisher);

$queueMap = [
    'PaymentWorker' => 'orders.payment',
    'AuditWorker' => 'orders.audit',
    'NotificationWorker' => 'orders.notification',
    'FulfillmentWorker' => 'orders.fulfillment',
    'InventoryWorker' => 'orders.inventory',
    'TrackingWorker' => 'orders.tracking',
];

$queue = $queueMap[$workerType] ?? throw new \RuntimeException("Unknown worker type: $workerType");

$worker = match ($workerType) {
    'PaymentWorker' => new PaymentWorker($channel, $redis, $pdo, $workerId, $orderService),
    'AuditWorker' => new AuditWorker($channel, $redis, $pdo, $workerId, $eventRepo, $readModel),
    'FulfillmentWorker' => new FulfillmentWorker($channel, $redis, $pdo, $workerId, $orderService),
    'InventoryWorker' => new InventoryWorker($channel, $redis, $pdo, $workerId),
    'NotificationWorker' => new NotificationWorker($channel, $redis, $pdo, $workerId, new SmtpMailer(
        $_ENV['MAIL_HOST'] ?? 'mailpit',
        (int)($_ENV['MAIL_PORT'] ?? 1025),
        $_ENV['MAIL_FROM'] ?? 'noreply@oms.local',
    )),
    'TrackingWorker' => new TrackingWorker($channel, $redis, $pdo, $workerId, $orderRepo),
};

$heartbeatInterval = (int)($_ENV['HEARTBEAT_INTERVAL'] ?? 5);

$channel->basic_qos(null, 1, null);
$channel->basic_consume($queue, '', false, false, false, false, [$worker, 'process']);

$worker->setWorkerInfo($workerType, $queue);

echo "[{$workerId}] Listening on {$queue}..." . PHP_EOL;

while ($channel->is_consuming()) {
    try {
        $channel->wait(null, false, $heartbeatInterval);
    } catch (\PhpAmqpLib\Exception\AMQPTimeoutException) {
        // timeout is expected — just send heartbeat and loop
    }
    $worker->sendHeartbeat();
}

$channel->close();
$amqp->close();
