<?php

use App\Events\OrderEvent;
use App\Services\EventPublisher;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

afterEach(fn() => Mockery::close());

it('publish calls basic_publish on exchange orders with the event routing key', function () {
    /** @var AMQPChannel&MockInterface $channel */
    $channel = Mockery::mock(AMQPChannel::class);
    $channel->shouldReceive('basic_publish')
        ->once()
        ->withArgs(function (AMQPMessage $msg, string $exchange, string $routingKey) {
            return $exchange === 'orders' && $routingKey === 'order.created';
        });

    $publisher = new EventPublisher($channel);
    $event = OrderEvent::create('order.created', 'order-uuid-123', ['total' => 100.0]);

    $publisher->publish($event);
});

it('publish encodes the event as JSON in the message body', function () {
    /** @var AMQPChannel&MockInterface $channel */
    $channel = Mockery::mock(AMQPChannel::class);
    $channel->shouldReceive('basic_publish')
        ->once()
        ->withArgs(function (AMQPMessage $msg) {
            $body = json_decode($msg->getBody(), true);
            return isset($body['event_id'], $body['event_type'], $body['order_id'], $body['occurred_at'], $body['payload']);
        });

    $publisher = new EventPublisher($channel);
    $event = OrderEvent::create('order.payment.approved', 'order-abc', ['total' => 50.0]);

    $publisher->publish($event);
});

it('publish sets the message content_type to application/json', function () {
    /** @var AMQPChannel&MockInterface $channel */
    $channel = Mockery::mock(AMQPChannel::class);
    $channel->shouldReceive('basic_publish')
        ->once()
        ->withArgs(function (AMQPMessage $msg) {
            return $msg->get('content_type') === 'application/json';
        });

    $publisher = new EventPublisher($channel);
    $event = OrderEvent::create('order.shipped', 'order-abc', []);

    $publisher->publish($event);
});

it('publish sets delivery_mode to persistent (2)', function () {
    /** @var AMQPChannel&MockInterface $channel */
    $channel = Mockery::mock(AMQPChannel::class);
    $channel->shouldReceive('basic_publish')
        ->once()
        ->withArgs(function (AMQPMessage $msg) {
            return $msg->get('delivery_mode') === AMQPMessage::DELIVERY_MODE_PERSISTENT;
        });

    $publisher = new EventPublisher($channel);
    $event = OrderEvent::create('order.cancelled', 'order-abc', []);

    $publisher->publish($event);
});

it('setupExchangesAndQueues declares exchange orders as durable topic', function () {
    /** @var AMQPChannel&MockInterface $channel */
    $channel = Mockery::mock(AMQPChannel::class);
    $channel->shouldReceive('exchange_declare')
        ->once()
        ->withArgs(function (string $name, string $type, bool $passive, bool $durable) {
            return $name === 'orders' && $type === 'topic' && $durable === true;
        });
    $channel->shouldReceive('queue_declare')->zeroOrMoreTimes();
    $channel->shouldReceive('queue_bind')->zeroOrMoreTimes();

    (new EventPublisher($channel))->setupExchangesAndQueues();
});

it('setupExchangesAndQueues declares all 8 queues', function () {
    /** @var AMQPChannel&MockInterface $channel */
    $channel = Mockery::mock(AMQPChannel::class);
    $channel->shouldReceive('exchange_declare')->once();
    $channel->shouldReceive('queue_declare')
        ->times(8)
        ->withArgs(function (string $queueName) {
            return in_array($queueName, [
                'orders.audit',
                'orders.payment',
                'orders.notification',
                'orders.inventory',
                'orders.tracking',
                'orders.fulfillment',
                'orders.dead',
                'orders.retry',
            ], true);
        });
    $channel->shouldReceive('queue_bind')->zeroOrMoreTimes();

    (new EventPublisher($channel))->setupExchangesAndQueues();
});

it('setupExchangesAndQueues binds orders.audit with wildcard order.#', function () {
    /** @var AMQPChannel&MockInterface $channel */
    $channel = Mockery::mock(AMQPChannel::class);
    $channel->shouldReceive('exchange_declare')->once();
    $channel->shouldReceive('queue_declare')->times(8);
    $channel->shouldReceive('queue_bind')
        ->withArgs(fn(string $q, string $ex, string $rk) => $q === 'orders.audit' && $rk === 'order.#')
        ->once();
    $channel->shouldReceive('queue_bind')->zeroOrMoreTimes();

    (new EventPublisher($channel))->setupExchangesAndQueues();
});
