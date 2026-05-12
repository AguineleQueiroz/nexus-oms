<?php

namespace App\Repositories;

class ReadModelRepository
{
    public function __construct(private readonly \Redis $redis) {}

    public function updateOrderSnapshot(string $orderId, array $snapshot): void
    {
        $this->redis->set("order:snapshot:{$orderId}", json_encode($snapshot));
    }

    public function getSnapshot(string $orderId): ?array
    {
        $data = $this->redis->get("order:snapshot:{$orderId}");
        return $data ? json_decode($data, true) : null;
    }
}
