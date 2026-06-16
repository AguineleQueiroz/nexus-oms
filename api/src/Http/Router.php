<?php

namespace App\Http;

class Router
{
    private const UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [strtoupper($method), $path, $handler];
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = strtok($request->getUri(), '?');

        foreach ($this->routes as [$routeMethod, $pattern, $handler]) {
            if ($routeMethod !== $method) {
                continue;
            }

            if (preg_match($this->toRegex($pattern), $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return $handler($request, $params);
            }
        }

        return Response::json(['error' => 'Not found'], 404);
    }

    private function toRegex(string $path): string
    {
        $pattern = preg_replace('/\{([a-z_]+)\}/', '(?P<$1>' . self::UUID . ')', $path);
        return '#^' . $pattern . '$#i';
    }
}
