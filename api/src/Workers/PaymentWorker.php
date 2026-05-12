<?php

namespace App\Workers;

use App\Services\OrderService;
use PhpAmqpLib\Channel\AMQPChannel;

class PaymentWorker extends BaseWorker
{
    public ?bool $forceApprove = null;

    public function __construct(
        AMQPChannel          $channel,
        \Redis               $redis,
        \PDO                 $pdo,
        string               $workerId,
        private readonly OrderService $orderService,
    ) {
        parent::__construct($channel, $redis, $pdo, $workerId);
    }

    protected function handle(array $event): void
    {
        if ($event['event_type'] !== 'order.payment.pending') {
            return;
        }

        $orderId = $event['order_id'];
        $approve = $this->forceApprove ?? (random_int(1, 100) <= 70);

        if ($approve) {
            $this->orderService->approvePayment($orderId);
        } else {
            $this->orderService->refusePayment($orderId);
        }
    }
}
