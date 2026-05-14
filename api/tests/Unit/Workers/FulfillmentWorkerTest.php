<?php

use App\Services\OrderService;
use App\Workers\FulfillmentWorker;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

afterEach(fn () => Mockery::close());

function fulfillmentMsg(string $eventType): AMQPMessage&\Mockery\MockInterface
{
    $msg = Mockery::mock(AMQPMessage::class);
    $msg->shouldReceive('get')->with('message_id')->andReturn('ful-evt-001');
    $msg->shouldReceive('get')->with('application_headers')->andThrow(new \OutOfBoundsException());
    $msg->shouldReceive('getBody')->andReturn(json_encode([
        'event_id'   => 'ful-evt-001',
        'event_type' => $eventType,
        'order_id'   => 'ord-ful-1',
        'occurred_at' => '2025-01-01T00:00:00Z',
        'payload'    => [],
    ]));
    $msg->shouldReceive('ack');
    return $msg;
}

function makeFulfillmentWorker(OrderService $service): FulfillmentWorker
{
    $channel = Mockery::mock(AMQPChannel::class);
    $redis   = Mockery::mock(Redis::class);
    $redis->shouldReceive('get')->andReturn(false)->byDefault();
    $redis->shouldReceive('setex')->byDefault();

    $pdo    = Mockery::mock(PDO::class);
    $select = Mockery::mock(PDOStatement::class);
    $insert = Mockery::mock(PDOStatement::class);
    $pdo->shouldReceive('prepare')->with(Mockery::pattern('/SELECT/'))->andReturn($select);
    $pdo->shouldReceive('prepare')->with(Mockery::pattern('/INSERT/'))->andReturn($insert);
    $select->shouldReceive('execute')->andReturn(true);
    $select->shouldReceive('fetch')->andReturn(false);
    $insert->shouldReceive('execute')->andReturn(true);

    return new FulfillmentWorker($channel, $redis, $pdo, 'fulfillment-worker-1', $service);
}

it('order.payment.approved calls OrderService::advance() to start picking', function () {
    $service = Mockery::mock(OrderService::class);
    $service->shouldReceive('advance')->once()->with('ord-ful-1')->andReturn([]);

    makeFulfillmentWorker($service)->process(fulfillmentMsg('order.payment.approved'));
});

it('other event types are ignored', function () {
    $service = Mockery::mock(OrderService::class);
    $service->shouldReceive('advance')->never();

    makeFulfillmentWorker($service)->process(fulfillmentMsg('order.created'));
});
