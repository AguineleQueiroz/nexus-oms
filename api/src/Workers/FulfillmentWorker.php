<?php

namespace App\Workers;

use App\Services\OrderService;
use PhpAmqpLib\Channel\AMQPChannel;

class FulfillmentWorker extends BaseWorker
{
    public function __construct(
        AMQPChannel                   $channel,
        \Redis                        $redis,
        \PDO                          $pdo,
        string                        $workerId,
        private readonly OrderService $orderService,
    )
    {
        parent::__construct($channel, $redis, $pdo, $workerId);
    }

    protected function handle(array $event): void
    {
        if ($event['event_type'] !== 'order.payment.approved') {
            return;
        }

        $this->orderService->advance($event['order_id']);
    }
}
