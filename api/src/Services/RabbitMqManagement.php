<?php

namespace App\Services;

class RabbitMqManagement
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $user = 'guest',
        private readonly string $password = 'guest',
    )
    {
    }

    public function getQueues(): array
    {
        $context = stream_context_create([
            'http' => [
                'header' => 'Authorization: Basic ' . base64_encode("{$this->user}:{$this->password}"),
                'timeout' => 3,
            ],
        ]);

        $json = @file_get_contents("{$this->baseUrl}/api/queues", false, $context);

        return $json ? json_decode($json, true) : [];
    }
}
