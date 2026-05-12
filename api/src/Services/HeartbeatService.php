<?php

namespace App\Services;

class HeartbeatService
{
    public function __construct(private readonly \Redis $redis) {}

    public function register(string $workerId, string $workerType, string $queueName): void
    {
        $this->redis->set("worker:heartbeat:{$workerId}", json_encode([
            'worker_id'        => $workerId,
            'worker_type'      => $workerType,
            'queue_name'       => $queueName,
            'status'           => 'active',
            'last_heartbeat'   => (new \DateTimeImmutable())->format('c'),
            'events_processed' => 0,
            'events_failed'    => 0,
            'started_at'       => (new \DateTimeImmutable())->format('c'),
        ]));
    }

    public function update(string $workerId): void
    {
        $key  = "worker:heartbeat:{$workerId}";
        $data = $this->redis->get($key);
        if ($data === false) {
            return;
        }
        $blob                   = json_decode($data, true);
        $blob['last_heartbeat'] = (new \DateTimeImmutable())->format('c');
        $blob['status']         = 'active';
        $this->redis->set($key, json_encode($blob));
    }

    public function getAll(): array
    {
        $keys    = $this->redis->keys('worker:heartbeat:*');
        $workers = [];

        foreach ($keys as $key) {
            $data = $this->redis->get($key);
            if ($data === false) {
                continue;
            }
            $worker           = json_decode($data, true);
            $worker['status'] = $this->getStatus($worker['worker_id'] ?? '');
            $workers[]        = $worker;
        }

        return $workers;
    }

    public function getStatus(string $workerId): string
    {
        $data = $this->redis->get("worker:heartbeat:{$workerId}");
        if ($data === false) {
            return 'stopped';
        }

        $blob      = json_decode($data, true);
        $last      = new \DateTimeImmutable($blob['last_heartbeat'] ?? 'now');
        $threshold = (int) ($_ENV['HEARTBEAT_INTERVAL'] ?? 5) * 3;
        $elapsed   = (new \DateTimeImmutable())->getTimestamp() - $last->getTimestamp();

        return $elapsed > $threshold ? 'idle' : 'active';
    }
}
