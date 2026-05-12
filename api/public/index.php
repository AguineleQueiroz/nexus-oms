<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Controllers\OrderController;
use App\Database\Connection;
use App\Http\Pipeline;
use App\Http\Request;
use App\Http\Response;
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
        $pdo        = Connection::getInstance();
        $orderRepo  = new OrderRepository($pdo);
        $eventRepo  = new EventRepository($pdo);
        $publisher  = new EventPublisher();
        $service    = new OrderService($orderRepo, $eventRepo, $publisher);
        $controller = new OrderController($service, $orderRepo, $eventRepo);

        $method = $req->getMethod();
        $path   = strtok($req->getUri(), '?');

        $uuidSeg = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

        // POST /api/orders
        if ($method === 'POST' && $path === '/api/orders') {
            return $controller->create($req);
        }

        // GET /api/orders
        if ($method === 'GET' && $path === '/api/orders') {
            return $controller->index($req);
        }

        // GET /api/orders/{id}
        if ($method === 'GET' && preg_match("#^/api/orders/({$uuidSeg})$#i", $path, $m)) {
            return $controller->show($req, $m[1]);
        }

        // POST /api/orders/{id}/pay
        if ($method === 'POST' && preg_match("#^/api/orders/({$uuidSeg})/pay$#i", $path, $m)) {
            return $controller->pay($req, $m[1]);
        }

        // POST /api/orders/{id}/refuse-payment
        if ($method === 'POST' && preg_match("#^/api/orders/({$uuidSeg})/refuse-payment$#i", $path, $m)) {
            return $controller->refusePayment($req, $m[1]);
        }

        // POST /api/orders/{id}/cancel
        if ($method === 'POST' && preg_match("#^/api/orders/({$uuidSeg})/cancel$#i", $path, $m)) {
            return $controller->cancel($req, $m[1]);
        }

        // POST /api/orders/{id}/advance
        if ($method === 'POST' && preg_match("#^/api/orders/({$uuidSeg})/advance$#i", $path, $m)) {
            return $controller->advance($req, $m[1]);
        }

        // Health check
        if ($method === 'GET' && $path === '/') {
            return Response::json(['status' => 'ok']);
        }

        return Response::json(['error' => 'Not found'], 404);
    }
);

$pipeline->run($request)->send();
