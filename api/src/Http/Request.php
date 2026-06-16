<?php

namespace App\Http;

use App\Exceptions\ValidationException;

class Request
{
    private ?array $parsedBody = null;

    private function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly string $rawBody,
        private readonly array  $queryParams,
        private readonly array  $headers,
    )
    {
    }

    public static function fromGlobals(): self
    {
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $_SERVER['REQUEST_URI'] ?? '/',
            (string)file_get_contents('php://input'),
            $_GET ?? [],
            function_exists('getallheaders') ? (getallheaders() ?: []) : [],
        );
    }

    public static function create(
        string $method,
        string $uri,
        string $body = '',
        array  $queryParams = [],
        array  $headers = [],
    ): self
    {
        return new self(strtoupper($method), $uri, $body, $queryParams, $headers);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        $normalized = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $normalized) {
                return $value;
            }
        }
        return null;
    }

    public function withParsedBody(array $data): self
    {
        $clone = new self($this->method, $this->uri, $this->rawBody, $this->queryParams, $this->headers);
        $clone->parsedBody = $data;
        return $clone;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function validate(array $rules): void
    {
        $body = $this->json();
        $errors = [];

        foreach ($rules as $field => $rule) {
            if ($rule === 'required' && (!isset($body[$field]) || $body[$field] === '')) {
                $errors[$field] = "{$field} is required";
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    public function json(): array
    {
        return $this->parsedBody ?? json_decode($this->rawBody, true) ?? [];
    }
}
