<?php

namespace App\Workers;

use App\Events\EventFactory;
use App\Repositories\EventRepository;
use App\Repositories\ReadModelRepository;
use PhpAmqpLib\Channel\AMQPChannel;

class AuditWorker extends BaseWorker
{
    public function __construct(
        AMQPChannel                          $channel,
        \Redis                               $redis,
        \PDO                                 $pdo,
        string                               $workerId,
        private readonly EventRepository     $eventRepo,
        private readonly ReadModelRepository $readModel,
    )
    {
        parent::__construct($channel, $redis, $pdo, $workerId);
    }

    protected function handle(array $event): void
    {
        try {
            $orderEvent = EventFactory::fromArray($event);
            $this->eventRepo->save($orderEvent, $event['event_type']);

            $snapshot = array_merge($event['payload'] ?? [], [
                'id' => $event['order_id'],
                'updated_at' => $event['occurred_at'],
            ]);
            $this->readModel->updateOrderSnapshot($event['order_id'], $snapshot);
        } catch (\Throwable) {
            // AuditWorker is the source of truth — never propagates failures
        }
    }
}
