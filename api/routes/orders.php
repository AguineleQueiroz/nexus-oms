<?php

use App\Controllers\OrderController;
use App\Http\Router;

/** @var Router $router */
/** @var OrderController $orderController */

$router->get('/api/orders', fn($req, $p) => $orderController->index($req));
$router->post('/api/orders', fn($req, $p) => $orderController->create($req));
$router->get('/api/orders/{id}', fn($req, $p) => $orderController->show($req, $p['id']));
$router->post('/api/orders/{id}/pay', fn($req, $p) => $orderController->pay($req, $p['id']));
$router->post('/api/orders/{id}/refuse-payment', fn($req, $p) => $orderController->refusePayment($req, $p['id']));
$router->post('/api/orders/{id}/cancel', fn($req, $p) => $orderController->cancel($req, $p['id']));
$router->post('/api/orders/{id}/advance', fn($req, $p) => $orderController->advance($req, $p['id']));
