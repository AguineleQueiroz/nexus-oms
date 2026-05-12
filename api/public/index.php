<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
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
use App\Services\EventPublisher;
use App\Services\OrderService;

Dotenv::createUnsafeImmutable(__DIR__ . '/..')->safeLoad();

$request = Request::fromGlobals();

$pipeline = new Pipeline(
    [new CorsMiddleware(), new JsonMiddleware()],
    function (Request $req) {
        $pdo            = Connection::getInstance();
        $orderRepo      = new OrderRepository($pdo);
        $eventRepo      = new EventRepository($pdo);
        $publisher      = new EventPublisher();
        $orderService   = new OrderService($orderRepo, $eventRepo, $publisher);
        $orderController = new OrderController($orderService, $orderRepo, $eventRepo);

        $router = new Router();
        $router->get('/', fn($r, $p) => Response::json(['status' => 'ok']));

        require __DIR__ . '/../routes/orders.php';

        return $router->dispatch($req);
    }
);

$pipeline->run($request)->send();
