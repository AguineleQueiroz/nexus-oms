<?php

namespace App\Services;

use App\Events\OrderEvent;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class EventPublisher
{
    private const EXCHANGE = 'orders';
    private const RETRY_TTL_BASE_MS = 30_000;

    public function __construct(private readonly AMQPChannel $channel) {}

    public function publish(OrderEvent $event): void
    {
        $message = new AMQPMessage(
            json_encode($event->toArray()),
            [
                'content_type'  => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id'    => $event->eventId,
            ]
        );

        $this->channel->basic_publish($message, self::EXCHANGE, $event->eventType);
    }

    public function setupExchangesAndQueues(): void
    {
        $this->channel->exchange_declare(self::EXCHANGE, 'topic', false, true, false);

        $this->declareDlq();
        $this->declareRetryQueue();
        $this->declareQueue('orders.audit');
        $this->declareQueue('orders.payment');
        $this->declareQueue('orders.notification');
        $this->declareQueue('orders.inventory');
        $this->declareQueue('orders.tracking');
        $this->declareQueue('orders.fulfillment');

        $this->bindQueues();
    }

    private function declareDlq(): void
    {
        $this->channel->queue_declare('orders.dead', false, true, false, false);
    }

    private function declareRetryQueue(): void
    {
        $this->channel->queue_declare('orders.retry', false, true, false, false, false, [
            'x-dead-letter-exchange'    => ['S', self::EXCHANGE],
            'x-message-ttl'             => ['I', self::RETRY_TTL_BASE_MS],
        ]);
    }

    private function declareQueue(string $name): void
    {
        $this->channel->queue_declare($name, false, true, false, false, false, [
            'x-dead-letter-exchange' => ['S', self::EXCHANGE],
            'x-dead-letter-routing-key' => ['S', 'orders.dead'],
        ]);
    }

    private function bindQueues(): void
    {
        $bindings = [
            ['orders.audit',        'order.*'],
            ['orders.payment',      'order.payment.*'],
            ['orders.notification', 'order.created'],
            ['orders.notification', 'order.payment.*'],
            ['orders.notification', 'order.shipped'],
            ['orders.notification', 'order.delivered'],
            ['orders.notification', 'order.cancelled'],
            ['orders.inventory',    'order.created'],
            ['orders.inventory',    'order.picking'],
            ['orders.inventory',    'order.cancelled'],
            ['orders.tracking',     'order.shipped'],
            ['orders.fulfillment',  'order.picking'],
        ];

        foreach ($bindings as [$queue, $routingKey]) {
            $this->channel->queue_bind($queue, self::EXCHANGE, $routingKey);
        }
    }
}
