<?php

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

class JsonMiddleware implements MiddlewareInterface
{
    private const BODY_METHODS = ['POST', 'PUT', 'PATCH'];

    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->getMethod(), self::BODY_METHODS, true)) {
            $contentType = $request->getHeader('Content-Type') ?? '';

            if (!str_contains($contentType, 'application/json')) {
                return Response::json(['error' => 'Unsupported Media Type'], 415);
            }

            $request = $request->withParsedBody(
                json_decode($request->getRawBody(), true) ?? []
            );
        }

        return $next($request);
    }
}
