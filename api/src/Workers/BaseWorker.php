<?php

namespace App\Workers;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

abstract class BaseWorker
{
    private const RETRY_QUEUE = 'orders.retry';
    private const DEAD_QUEUE  = 'orders.dead';

    private string $workerType  = '';
    private string $queueName   = '';
    private int    $eventsProcessed = 0;
    private int    $eventsFailed    = 0;
    private string $startedAt;

    public function __construct(
        protected readonly AMQPChannel $channel,
        protected readonly \Redis      $redis,
        protected readonly \PDO        $pdo,
        protected readonly string      $workerId,
    ) {
        $this->startedAt = (new \DateTimeImmutable())->format('c');
        $this->loadCountersFromRedis();
    }

    private function loadCountersFromRedis(): void
    {
        $data = $this->redis->get("worker:heartbeat:{$this->workerId}");
        if ($data === false) {
            return;
        }
        $blob = json_decode($data, true);
        $this->eventsProcessed = (int) ($blob['events_processed'] ?? 0);
        $this->eventsFailed    = (int) ($blob['events_failed'] ?? 0);
    }

    public function setWorkerInfo(string $workerType, string $queueName): void
    {
        $this->workerType = $workerType;
        $this->queueName  = $queueName;
    }

    abstract protected function handle(array $event): void;

    public function process(AMQPMessage $msg): void
    {
        $eventId    = $msg->get('message_id') ?? '';
        $retryCount = $this->retryCount($msg);

        if ($this->isProcessed($eventId)) {
            $msg->ack();
            return;
        }

        $event = json_decode($msg->getBody(), true) ?? [];

        try {
            $this->handle($event);
            $this->markProcessed($eventId);
            $this->eventsProcessed++;
            $msg->ack();
        } catch (\Throwable $e) {
            $this->eventsFailed++;
            $this->handleFailure($msg, $retryCount + 1);
        }
    }

    public function sendHeartbeat(): void
    {
        $interval = (int) ($_ENV['HEARTBEAT_INTERVAL'] ?? 5);
        $this->redis->setex(
            "worker:heartbeat:{$this->workerId}",
            $interval * 3,
            json_encode([
                'worker_id'        => $this->workerId,
                'worker_type'      => $this->workerType,
                'queue_name'       => $this->queueName,
                'status'           => 'active',
                'last_heartbeat'   => (new \DateTimeImmutable())->format('c'),
                'events_processed' => $this->eventsProcessed,
                'events_failed'    => $this->eventsFailed,
                'started_at'       => $this->startedAt,
            ])
        );
    }

    private function handleFailure(AMQPMessage $msg, int $attempt): void
    {
        $maxAttempts = (int) ($_ENV['MAX_ATTEMPTS'] ?? 3);
        $baseTtlSec  = (int) ($_ENV['RETRY_BASE_TTL'] ?? 30);

        if ($attempt >= $maxAttempts) {
            $dead = new AMQPMessage(
                $msg->getBody(),
                ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            );
            $this->channel->basic_publish($dead, '', self::DEAD_QUEUE);
            $msg->nack(false, false);
            return;
        }

        $ttlMs   = $baseTtlSec * $attempt * 1000;
        $headers = new AMQPTable(['x-retry-count' => $attempt]);
        $retry   = new AMQPMessage(
            $msg->getBody(),
            [
                'delivery_mode'       => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id'          => $msg->get('message_id'),
                'expiration'          => (string) $ttlMs,
                'application_headers' => $headers,
            ]
        );
        $this->channel->basic_publish($retry, '', self::RETRY_QUEUE);
        $msg->ack();
    }

    private function isProcessed(string $eventId): bool
    {
        if ($eventId === '') {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM processed_events WHERE event_id = :id');
        $stmt->execute([':id' => $eventId]);
        return (bool) $stmt->fetch();
    }

    private function markProcessed(string $eventId): void
    {
        if ($eventId === '') {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO processed_events (event_id) VALUES (:id) ON CONFLICT DO NOTHING'
        );
        $stmt->execute([':id' => $eventId]);
    }

    private function retryCount(AMQPMessage $msg): int
    {
        try {
            $headers = $msg->get('application_headers');
            return (int) ($headers->getNativeData()['x-retry-count'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
