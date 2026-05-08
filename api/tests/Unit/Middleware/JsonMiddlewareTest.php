<?php

use App\Http\Request;
use App\Http\Response;
use App\Middleware\JsonMiddleware;

it('returns 415 for POST without Content-Type application/json', function () {
    $json    = new JsonMiddleware();
    $request = Request::create('POST', '/api/orders', '{"name":"test"}', [], [
        'Content-Type' => 'text/plain',
    ]);
    $next = fn (Request $req) => Response::json(['ok' => true]);

    $response = $json->handle($request, $next);

    expect($response->getStatus())->toBe(415);
});

it('parses valid JSON body and passes it to next for POST requests', function () {
    $json    = new JsonMiddleware();
    $payload = ['customer_name' => 'João'];
    $request = Request::create('POST', '/api/orders', json_encode($payload), [], [
        'Content-Type' => 'application/json',
    ]);

    $received = null;
    $next     = function (Request $req) use (&$received): Response {
        $received = $req->json();
        return Response::json(['ok' => true]);
    };

    $json->handle($request, $next);

    expect($received)->toBe($payload);
});
