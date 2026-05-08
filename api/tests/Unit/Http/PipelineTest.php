<?php

use App\Http\Pipeline;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\MiddlewareInterface;

it('executes two middlewares in order', function () {
    $order = [];

    $m1 = new class ($order) implements MiddlewareInterface {
        public function __construct(private array &$order) {}
        public function handle(Request $req, callable $next): Response
        {
            $this->order[] = 'first';
            return $next($req);
        }
    };

    $m2 = new class ($order) implements MiddlewareInterface {
        public function __construct(private array &$order) {}
        public function handle(Request $req, callable $next): Response
        {
            $this->order[] = 'second';
            return $next($req);
        }
    };

    $pipeline = new Pipeline([$m1, $m2], fn (Request $req) => Response::json(['ok' => true]));
    $pipeline->run(Request::create('GET', '/'));

    expect($order)->toBe(['first', 'second']);
});

it('middleware that does not call next short-circuits the pipeline', function () {
    $handlerCalled = false;

    $blocker = new class implements MiddlewareInterface {
        public function handle(Request $req, callable $next): Response
        {
            return Response::json(['blocked' => true], 403);
        }
    };

    $handler  = function () use (&$handlerCalled): Response {
        $handlerCalled = true;
        return Response::json(['ok' => true]);
    };

    $response = (new Pipeline([$blocker], $handler))->run(Request::create('GET', '/'));

    expect($handlerCalled)->toBeFalse()
        ->and($response->getStatus())->toBe(403);
});

it('final handler is called after all middlewares pass through', function () {
    $handlerCalled = false;

    $passthrough = new class implements MiddlewareInterface {
        public function handle(Request $req, callable $next): Response { return $next($req); }
    };

    $handler = function () use (&$handlerCalled): Response {
        $handlerCalled = true;
        return Response::json(['ok' => true]);
    };

    (new Pipeline([$passthrough], $handler))->run(Request::create('GET', '/'));

    expect($handlerCalled)->toBeTrue();
});
