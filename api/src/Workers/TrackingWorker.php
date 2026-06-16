<?php

namespace App\Workers;

use App\Repositories\OrderRepository;
use PhpAmqpLib\Channel\AMQPChannel;

class TrackingWorker extends BaseWorker
{
    public function __construct(
        AMQPChannel                      $channel,
        \Redis                           $redis,
        \PDO                             $pdo,
        string                           $workerId,
        private readonly OrderRepository $orderRepo,
    )
    {
        parent::__construct($channel, $redis, $pdo, $workerId);
    }

    protected function handle(array $event): void
    {
        if ($event['event_type'] !== 'order.shipped') {
            return;
        }

        $trackingCode = 'BR' . str_pad((string)random_int(0, 999_999_999), 9, '0', STR_PAD_LEFT);

        $this->orderRepo->updateMetadata($event['order_id'], ['tracking_code' => $trackingCode]);
    }
}
