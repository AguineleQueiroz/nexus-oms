<?php

namespace App\Repositories;

use PDO;

class ReadModelRepository
{
    private const LIFECYCLE = [
        'created', 'payment_pending', 'paid', 'picking',
        'shipped', 'delivered', 'cancelled', 'payment_refused',
    ];

    public function __construct(
        private readonly \Redis $redis,
        private readonly ?PDO   $pdo = null,
    ) {}

    // --- Write side (called by AuditWorker) ---

    public function updateOrderSnapshot(string $orderId, array $snapshot): void
    {
        $this->redis->set("order:snapshot:{$orderId}", json_encode($snapshot));
    }

    public function getSnapshot(string $orderId): ?array
    {
        $data = $this->redis->get("order:snapshot:{$orderId}");
        return $data ? json_decode($data, true) : null;
    }

    // --- Read side (called by DashboardController) ---

    public function getOrderStats(): array
    {
        $stmt = $this->pdo()->query('SELECT status, COUNT(*) as count FROM orders GROUP BY status');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $counts = array_fill_keys(self::LIFECYCLE, 0);
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }
        $counts['total'] = array_sum($counts);

        return $counts;
    }

    public function getEventStats(): array
    {
        $stmt = $this->pdo()->query("
            SELECT
                COUNT(*) FILTER (WHERE published_at > NOW() - INTERVAL '1 hour')
                    AS published_last_hour,
                COUNT(*) FILTER (WHERE published_at > NOW() - INTERVAL '1 hour' AND processed = TRUE)
                    AS processed_last_hour,
                COUNT(*) FILTER (WHERE published_at > NOW() - INTERVAL '1 hour' AND processed = FALSE AND error IS NOT NULL)
                    AS failed_last_hour
            FROM order_events
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'published_last_hour'  => (int) ($row['published_last_hour']  ?? 0),
            'processed_last_hour'  => (int) ($row['processed_last_hour']  ?? 0),
            'failed_last_hour'     => (int) ($row['failed_last_hour']     ?? 0),
            'dead'                 => 0,
        ];
    }

    public function getThroughput(): array
    {
        $stmt = $this->pdo()->query("
            SELECT
                to_char(date_trunc('minute', created_at), 'HH24:MI') AS minute,
                COUNT(*) AS count
            FROM orders
            WHERE created_at > NOW() - INTERVAL '1 hour'
            GROUP BY date_trunc('minute', created_at)
            ORDER BY date_trunc('minute', created_at)
        ");

        return array_map(
            fn($r) => ['minute' => $r['minute'], 'count' => (int) $r['count']],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function getFunnel(): array
    {
        $stmt   = $this->pdo()->query('SELECT status, COUNT(*) as count FROM orders GROUP BY status');
        $counts = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'count', 'status');

        return array_map(
            fn($s) => ['status' => $s, 'count' => (int) ($counts[$s] ?? 0)],
            self::LIFECYCLE
        );
    }

    public function getEventFeed(int $limit = 50): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM order_events ORDER BY published_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(function ($row) {
            if (isset($row['payload']) && is_string($row['payload'])) {
                $row['payload'] = json_decode($row['payload'], true);
            }
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getEventsByType(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT event_type, COUNT(*) as count FROM order_events GROUP BY event_type ORDER BY count DESC'
        );

        return array_map(
            fn($r) => ['event_type' => $r['event_type'], 'count' => (int) $r['count']],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    private function pdo(): PDO
    {
        if ($this->pdo === null) {
            throw new \LogicException('PDO is required for read-side operations.');
        }
        return $this->pdo;
    }
}
