<?php

use App\Http\Request;
use App\Http\Response;
use App\Middleware\CorsMiddleware;

it('OPTIONS preflight returns 204 with Access-Control headers', function () {
    $cors     = new CorsMiddleware();
    $request  = Request::create('OPTIONS', '/api/orders');
    $next     = fn (Request $req) => Response::json(['ok' => true]);

    $response = $cors->handle($request, $next);

    expect($response->getStatus())->toBe(204)
        ->and($response->getHeaders())->toHaveKey('Access-Control-Allow-Origin')
        ->and($response->getHeaders())->toHaveKey('Access-Control-Allow-Methods')
        ->and($response->getHeaders())->toHaveKey('Access-Control-Allow-Headers');
});

it('non-OPTIONS requests pass through to next with CORS headers added', function () {
    $cors    = new CorsMiddleware();
    $request = Request::create('GET', '/api/orders');
    $next    = fn (Request $req) => Response::json(['data' => []], 200);

    $response = $cors->handle($request, $next);

    expect($response->getStatus())->toBe(200)
        ->and($response->getHeaders())->toHaveKey('Access-Control-Allow-Origin');
});
