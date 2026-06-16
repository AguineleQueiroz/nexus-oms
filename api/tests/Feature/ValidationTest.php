<?php

use App\Http\Pipeline;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\CorsMiddleware;
use App\Middleware\JsonMiddleware;

function makePipeline(): Pipeline
{
    return new Pipeline(
        [new CorsMiddleware(), new JsonMiddleware()],
        fn(Request $req) => Response::json(['ok' => true])
    );
}

it('POST with Content-Type text/plain returns 415', function () {
    $req = Request::create('POST', '/test', '{}', [], ['Content-Type' => 'text/plain']);
    $res = makePipeline()->run($req);

    expect($res->getStatus())->toBe(415);
});

it('OPTIONS preflight returns 204 with Access-Control-Allow-Origin header', function () {
    $req = Request::create('OPTIONS', '/test');
    $res = makePipeline()->run($req);

    expect($res->getStatus())->toBe(204);
    expect($res->getHeaders())->toHaveKey('Access-Control-Allow-Origin');
});

it('GET request passes through with CORS headers in response', function () {
    $req = Request::create('GET', '/test');
    $res = makePipeline()->run($req);

    expect($res->getStatus())->toBe(200);
    expect($res->getHeaders())->toHaveKey('Access-Control-Allow-Origin');
});

it('POST with application/json Content-Type passes through to handler', function () {
    $req = Request::create('POST', '/test', '{"ok":true}', [], ['Content-Type' => 'application/json']);
    $res = makePipeline()->run($req);

    expect($res->getStatus())->toBe(200);
});
