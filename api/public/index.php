<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Http\Pipeline;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\CorsMiddleware;
use App\Middleware\JsonMiddleware;

Dotenv::createUnsafeImmutable(__DIR__ . '/..')->safeLoad();

$request = Request::fromGlobals();

$pipeline = new Pipeline(
    [new CorsMiddleware(), new JsonMiddleware()],
    fn (Request $req) => Response::json(['status' => 'ok']),
);

$pipeline->run($request)->send();
