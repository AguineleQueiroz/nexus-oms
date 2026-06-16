<?php

use App\Controllers\DashboardController;
use App\Http\Router;

/** @var Router $router */
/** @var DashboardController $dashboardController */

$router->get('/api/dashboard/stats', fn($req, $p) => $dashboardController->stats($req));
$router->get('/api/dashboard/throughput', fn($req, $p) => $dashboardController->throughput($req));
$router->get('/api/dashboard/funnel', fn($req, $p) => $dashboardController->funnel($req));
$router->get('/api/dashboard/consumers', fn($req, $p) => $dashboardController->consumers($req));
$router->get('/api/dashboard/events/feed', fn($req, $p) => $dashboardController->eventFeed($req));
$router->get('/api/dashboard/events/by-type', fn($req, $p) => $dashboardController->eventsByType($req));
$router->get('/api/dashboard/queues', fn($req, $p) => $dashboardController->queues($req));
$router->get('/api/dashboard/notifications', fn($req, $p) => $dashboardController->notifications($req));
