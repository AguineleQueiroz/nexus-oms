<?php

use App\Repositories\OrderRepository;
use App\Workers\TrackingWorker;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

afterEach(fn () => Mockery::close());

function trackingMsg(): AMQPMessage&\Mockery\MockInterface
{
    $msg = Mockery::mock(AMQPMessage::class);
    $msg->shouldReceive('get')->with('message_id')->andReturn('trk-evt-001');
    $msg->shouldReceive('get')->with('application_headers')->andThrow(new \OutOfBoundsException());
    $msg->shouldReceive('getBody')->andReturn(json_encode([
        'event_id'   => 'trk-evt-001',
        'event_type' => 'order.shipped',
        'order_id'   => 'ord-trk-1',
        'occurred_at' => '2025-01-01T00:00:00Z',
        'payload'    => [],
    ]));
    $msg->shouldReceive('ack');
    return $msg;
}

it('order.shipped generates a BR tracking code and updates order metadata', function () {
    $orderRepo = Mockery::mock(OrderRepository::class);
    $orderRepo->shouldReceive('updateMetadata')
        ->once()
        ->withArgs(function (string $orderId, array $metadata) {
            return $orderId === 'ord-trk-1'
                && isset($metadata['tracking_code'])
                && preg_match('/^BR\d{9}$/', $metadata['tracking_code']);
        });

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

    $worker = new TrackingWorker($channel, $redis, $pdo, 'tracking-worker-1', $orderRepo);
    $worker->process(trackingMsg());
});
