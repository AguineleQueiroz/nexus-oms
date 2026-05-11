<?php

namespace App\Http;

readonly class Response
{
    private function __construct(
        private int   $status,
        private array $headers,
        private mixed $body,
    ) {}

    public static function json(mixed $data, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'application/json'], $data);
    }

    public function withHeaders(array $extra): self
    {
        return new self($this->status, array_merge($this->headers, $extra), $this->body);
    }

    public function getStatus(): int    { return $this->status; }
    public function getHeaders(): array { return $this->headers; }
    public function getBody(): mixed    { return $this->body; }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo json_encode($this->body);
    }
}
