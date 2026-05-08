<?php

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

class CorsMiddleware implements MiddlewareInterface
{
    private const HEADERS = [
        'Access-Control-Allow-Origin'  => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
    ];

    public function handle(Request $request, callable $next): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            return Response::json(null, 204)->withHeaders(self::HEADERS);
        }

        return $next($request)->withHeaders(self::HEADERS);
    }
}
