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
use App\Services\MailpitService;
use App\Services\OrderService;
use App\Services\RabbitMqManagement;
use PhpAmqpLib\Connection\AMQPStreamConnection;

Dotenv::createUnsafeImmutable(__DIR__ . '/..')->safeLoad();

$request = Request::fromGlobals();

$pipeline = new Pipeline(
    [new CorsMiddleware(), new JsonMiddleware()],
    function (Request $req) {
        $pdo   = Connection::getInstance();
        $redis = new Redis();
        $redis->connect($_ENV['REDIS_HOST'] ?? 'redis', (int) ($_ENV['REDIS_PORT'] ?? 6379));

        $orderRepo = new OrderRepository($pdo);
        $eventRepo = new EventRepository($pdo);

        $readModel  = new ReadModelRepository($redis, $pdo);
        $heartbeat  = new HeartbeatService($redis);
        $rabbitMq   = new RabbitMqManagement(
            $_ENV['RABBITMQ_MANAGEMENT_URL'] ?? 'http://rabbitmq:15672',
            $_ENV['RABBITMQ_USER'] ?? 'guest',
            $_ENV['RABBITMQ_PASSWORD'] ?? 'guest',
        );
        $mailpit    = new MailpitService(
            $_ENV['MAILPIT_URL'] ?? 'http://mailpit:8025',
        );
        $dashboardController = new DashboardController($readModel, $heartbeat, $rabbitMq, $mailpit);

        $router = new Router();
        $router->get('/', fn($r, $p) => Response::json(['status' => 'ok']));

        require __DIR__ . '/../routes/dashboard.php';

        /**
         * Order routes require AMQP — connect only if necessary.
         */
        try {
            $amqp    = new AMQPStreamConnection(
                $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq',
                (int) ($_ENV['RABBITMQ_PORT'] ?? 5672),
                $_ENV['RABBITMQ_USER'] ?? 'guest',
                $_ENV['RABBITMQ_PASSWORD'] ?? 'guest',
            );
            $channel      = $amqp->channel();
            $publisher    = new EventPublisher($channel);
            $orderService = new OrderService($orderRepo, $eventRepo, $publisher);

            $orderController = new OrderController($orderService, $orderRepo, $eventRepo);
            require __DIR__ . '/../routes/orders.php';
        } catch (\Throwable) {
            $router->get('/api/orders', fn() => Response::json(['error' => 'Message broker unavailable'], 503));
            $router->post('/api/orders', fn() => Response::json(['error' => 'Message broker unavailable'], 503));
        }

        return $router->dispatch($req);
    }
);

$pipeline->run($request)->send();
