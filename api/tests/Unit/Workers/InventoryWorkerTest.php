<?php

use App\Workers\InventoryWorker;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

afterEach(fn () => Mockery::close());

function inventoryMsg(string $eventType): AMQPMessage&\Mockery\MockInterface
{
    $msg = Mockery::mock(AMQPMessage::class);
    $msg->shouldReceive('get')->with('message_id')->andReturn('inv-evt-001');
    $msg->shouldReceive('get')->with('application_headers')->andThrow(new \OutOfBoundsException());
    $msg->shouldReceive('getBody')->andReturn(json_encode([
        'event_id'   => 'inv-evt-001',
        'event_type' => $eventType,
        'order_id'   => 'ord-inv-1',
        'occurred_at' => '2025-01-01T00:00:00Z',
        'payload'    => ['items' => [['product' => 'Tênis', 'qty' => 1]]],
    ]));
    $msg->shouldReceive('ack');
    return $msg;
}

function makeInventoryWorker(): InventoryWorker
{
    $channel = Mockery::mock(AMQPChannel::class);
    $redis   = Mockery::mock(Redis::class);
    $redis->shouldReceive('setex')->byDefault();

    $pdo    = Mockery::mock(PDO::class);
    $select = Mockery::mock(PDOStatement::class);
    $insert = Mockery::mock(PDOStatement::class);
    $pdo->shouldReceive('prepare')->with(Mockery::pattern('/SELECT/'))->andReturn($select);
    $pdo->shouldReceive('prepare')->with(Mockery::pattern('/INSERT/'))->andReturn($insert);
    $select->shouldReceive('execute')->andReturn(true);
    $select->shouldReceive('fetch')->andReturn(false);
    $insert->shouldReceive('execute')->andReturn(true);

    return new InventoryWorker($channel, $redis, $pdo, 'inventory-worker-1');
}

it('order.created reserves stock without throwing', function () {
    expect(fn () => makeInventoryWorker()->process(inventoryMsg('order.created')))
        ->not->toThrow(\Throwable::class);
});

it('order.picking deducts stock without throwing', function () {
    expect(fn () => makeInventoryWorker()->process(inventoryMsg('order.picking')))
        ->not->toThrow(\Throwable::class);
});

it('order.cancelled releases stock without throwing', function () {
    expect(fn () => makeInventoryWorker()->process(inventoryMsg('order.cancelled')))
        ->not->toThrow(\Throwable::class);
});
