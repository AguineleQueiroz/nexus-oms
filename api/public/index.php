<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Controllers\DashboardController;
use App\Controllers\OrderController;
use App\Database\Connection;
use App\Http\Pipeline;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Middleware\CorsMiddleware;
use App\Middleware\JsonMiddleware;
use App\Repositories\EventRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ReadModelRepository;
use App\Services\EventPublisher;
use App\Services\HeartbeatService;
use App\Services\OrderService;
use App\Services\RabbitMqManagement;

Dotenv::createUnsafeImmutable(__DIR__ . '/..')->safeLoad();

$request = Request::fromGlobals();

$pipeline = new Pipeline(
    [new CorsMiddleware(), new JsonMiddleware()],
    function (Request $req) {
        $pdo   = Connection::getInstance();
        $redis = new Redis();
        $redis->connect($_ENV['REDIS_HOST'] ?? 'redis', (int) ($_ENV['REDIS_PORT'] ?? 6379));

        $orderRepo   = new OrderRepository($pdo);
        $eventRepo   = new EventRepository($pdo);
        $publisher   = new EventPublisher();
        $orderService = new OrderService($orderRepo, $eventRepo, $publisher);

        $orderController = new OrderController($orderService, $orderRepo, $eventRepo);

        $readModel  = new ReadModelRepository($redis, $pdo);
        $heartbeat  = new HeartbeatService($redis);
        $rabbitMq   = new RabbitMqManagement(
            $_ENV['RABBITMQ_MANAGEMENT_URL'] ?? 'http://rabbitmq:15672',
            $_ENV['RABBITMQ_USER'] ?? 'guest',
            $_ENV['RABBITMQ_PASSWORD'] ?? 'guest',
        );
        $dashboardController = new DashboardController($readModel, $heartbeat, $rabbitMq);

        $router = new Router();
        $router->get('/', fn($r, $p) => Response::json(['status' => 'ok']));

        require __DIR__ . '/../routes/orders.php';
        require __DIR__ . '/../routes/dashboard.php';

        return $router->dispatch($req);
    }
);

$pipeline->run($request)->send();
