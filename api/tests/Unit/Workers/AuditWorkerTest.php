<?php

use App\Repositories\EventRepository;
use App\Repositories\ReadModelRepository;
use App\Workers\AuditWorker;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

afterEach(fn () => Mockery::close());

function auditMsg(string $eventType = 'order.created'): AMQPMessage&\Mockery\MockInterface
{
    $payload = [
        'event_id'   => 'audit-evt-001',
        'event_type' => $eventType,
        'order_id'   => 'ord-audit-1',
        'occurred_at' => '2025-01-01T00:00:00Z',
        'payload'    => ['customer_name' => 'Ana', 'total' => 100.0, 'status' => 'created'],
    ];

    $msg = Mockery::mock(AMQPMessage::class);
    $msg->shouldReceive('get')->with('message_id')->andReturn('audit-evt-001');
    $msg->shouldReceive('get')->with('application_headers')->andThrow(new \OutOfBoundsException());
    $msg->shouldReceive('getBody')->andReturn(json_encode($payload));
    $msg->shouldReceive('ack');
    return $msg;
}

function makeAuditWorker(EventRepository $eventRepo, ReadModelRepository $readModel): AuditWorker
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

    return new AuditWorker($channel, $redis, $pdo, 'audit-worker-1', $eventRepo, $readModel);
}

it('handle() saves the event to order_events via EventRepository', function () {
    $eventRepo = Mockery::mock(EventRepository::class);
    $eventRepo->shouldReceive('save')->once();

    $readModel = Mockery::mock(ReadModelRepository::class);
    $readModel->shouldReceive('updateOrderSnapshot')->once();

    makeAuditWorker($eventRepo, $readModel)->process(auditMsg());
});

it('handle() calls ReadModelRepository::updateOrderSnapshot() with order_id', function () {
    $eventRepo = Mockery::mock(EventRepository::class);
    $eventRepo->shouldReceive('save');

    $readModel = Mockery::mock(ReadModelRepository::class);
    $readModel->shouldReceive('updateOrderSnapshot')
        ->once()
        ->withArgs(fn (string $id) => $id === 'ord-audit-1');

    makeAuditWorker($eventRepo, $readModel)->process(auditMsg());
});

it('handle() does not re-throw on DB error — AuditWorker never fails', function () {
    $eventRepo = Mockery::mock(EventRepository::class);
    $eventRepo->shouldReceive('save')->andThrow(new \RuntimeException('DB error'));

    $readModel = Mockery::mock(ReadModelRepository::class);
    $readModel->shouldReceive('updateOrderSnapshot')->never();

    expect(fn () => makeAuditWorker($eventRepo, $readModel)->process(auditMsg()))
        ->not->toThrow(\RuntimeException::class);
});
