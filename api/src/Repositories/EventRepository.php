<?php

namespace App\Repositories;

use App\Events\OrderEvent;
use PDO;

readonly class EventRepository
{
    public function __construct(private PDO $pdo) {}

    public function save(OrderEvent $event, string $routingKey, ?string $workerId = null): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO order_events (id, order_id, event_type, routing_key, payload, worker_id)
            VALUES (:id, :order_id, :event_type, :routing_key, :payload, :worker_id)
        ');

        $stmt->execute([
            ':id'          => $event->eventId,
            ':order_id'    => $event->orderId,
            ':event_type'  => $event->eventType,
            ':routing_key' => $routingKey,
            ':payload'     => json_encode($event->payload),
            ':worker_id'   => $workerId,
        ]);
    }

    public function findByOrderId(string $orderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM order_events WHERE order_id = :order_id ORDER BY published_at ASC'
        );
        $stmt->execute([':order_id' => $orderId]);

        return array_map([$this, 'decodeRow'], $stmt->fetchAll());
    }

    public function markProcessed(string $eventId, string $workerId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE order_events
            SET processed = TRUE, processed_at = NOW(), worker_id = :worker_id
            WHERE id = :id
        ');
        $stmt->execute([':worker_id' => $workerId, ':id' => $eventId]);
    }

    private function decodeRow(array $row): array
    {
        if (isset($row['payload']) && is_string($row['payload'])) {
            $row['payload'] = json_decode($row['payload'], true);
        }
        if (isset($row['processed'])) {
            $row['processed'] = (bool) $row['processed'];
        }
        return $row;
    }
}
