<?php

namespace App\Services;

use Exception;

class HeartbeatService
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly ?\PDO  $pdo = null,
    )
    {
    }

    public function register(string $workerId, string $workerType, string $queueName): void
    {
        $now = (new \DateTimeImmutable())->format('c');
        $blob = [
            'worker_id' => $workerId,
            'worker_type' => $workerType,
            'queue_name' => $queueName,
            'status' => 'active',
            'last_heartbeat' => $now,
            'events_processed' => 0,
            'events_failed' => 0,
            'started_at' => $now,
        ];

        // Preserve existing counters so a worker restart doesn't zero the dashboard
        $existing = $this->redis->get("worker:heartbeat:{$workerId}");
        if ($existing !== false) {
            $prev = json_decode($existing, true);
            $blob['events_processed'] = (int)($prev['events_processed'] ?? 0);
            $blob['events_failed'] = (int)($prev['events_failed'] ?? 0);
            $blob['started_at'] = $prev['started_at'] ?? $now;
        }

        $this->redis->set("worker:heartbeat:{$workerId}", json_encode($blob));
    }

    public function update(string $workerId): void
    {
        $key = "worker:heartbeat:{$workerId}";
        $data = $this->redis->get($key);
        if ($data === false) {
            return;
        }
        $blob = json_decode($data, true);
        $blob['last_heartbeat'] = (new \DateTimeImmutable())->format('c');
        $blob['status'] = 'active';
        $this->redis->set($key, json_encode($blob));
    }

    public function getAll(): array
    {
        // Base: consumers_log — persists across Redis TTL expiry
        $dbWorkers = $this->fetchFromDb();

        // Overlay: Redis — real-time counters and status for live workers
        $redisById = [];
        foreach ($this->redis->keys('worker:heartbeat:*') as $key) {
            $data = $this->redis->get($key);
            if ($data === false) {
                continue;
            }
            $blob = json_decode($data, true);
            $id = $blob['worker_id'] ?? '';
            if ($id !== '') {
                $blob['status'] = $this->getStatus($id);
                $redisById[$id] = $blob;
            }
        }

        $workers = [];
        $seenIds = [];

        foreach ($dbWorkers as $row) {
            $id = $row['worker_id'];
            $seenIds[] = $id;
            $workers[] = $redisById[$id] ?? $this->rowToWorker($row);
        }

        // Workers only in Redis (first heartbeat not yet persisted to DB)
        foreach ($redisById as $id => $blob) {
            if (!in_array($id, $seenIds, true)) {
                $workers[] = $blob;
            }
        }

        return $workers;
    }

    private function fetchFromDb(): array
    {
        if ($this->pdo === null) {
            return [];
        }
        $stmt = $this->pdo->query('SELECT * FROM consumers_log ORDER BY worker_type');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @throws Exception
     */
    public function getStatus(string $workerId): string
    {
        $data = $this->redis->get("worker:heartbeat:{$workerId}");
        if ($data === false) {
            return 'stopped';
        }

        $blob = json_decode($data, true);
        $last = new \DateTimeImmutable($blob['last_heartbeat'] ?? 'now');
        $threshold = (int)($_ENV['HEARTBEAT_INTERVAL'] ?? 5) * 3;
        $elapsed = (new \DateTimeImmutable())->getTimestamp() - $last->getTimestamp();

        return $elapsed > $threshold ? 'idle' : 'active';
    }

    /**
     * @throws Exception
     */
    private function rowToWorker(array $row): array
    {
        $last = new \DateTimeImmutable($row['last_heartbeat']);
        $threshold = (int)($_ENV['HEARTBEAT_INTERVAL'] ?? 5) * 3;
        $elapsed = (new \DateTimeImmutable())->getTimestamp() - $last->getTimestamp();

        return [
            'worker_id' => $row['worker_id'],
            'worker_type' => $row['worker_type'],
            'queue_name' => $row['queue_name'],
            'status' => $elapsed > $threshold ? 'idle' : 'active',
            'last_heartbeat' => $row['last_heartbeat'],
            'events_processed' => (int)$row['events_processed'],
            'events_failed' => (int)$row['events_failed'],
            'started_at' => $row['started_at'],
        ];
    }
}
