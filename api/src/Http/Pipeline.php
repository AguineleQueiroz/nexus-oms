<?php

namespace App\Http;

use App\Middleware\MiddlewareInterface;

class Pipeline
{
    /** @var callable */
    private $handler;

    public function __construct(
        private readonly array $middlewares,
        callable               $handler,
    )
    {
        $this->handler = $handler;
    }

    public function run(Request $request): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            fn(callable $next, MiddlewareInterface $middleware) => fn(Request $req) => $middleware->handle($req, $next),
            $this->handler,
        );

        return ($pipeline)($request);
    }
}
