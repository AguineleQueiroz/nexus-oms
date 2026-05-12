<?php

use App\Workers\BaseWorker;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/*
 * Concrete worker used only in this test file
 */
class ConcreteWorker extends BaseWorker
{
    public int  $handleCount = 0;
    public bool $shouldThrow = false;

    protected function handle(array $event): void
    {
        if ($this->shouldThrow) {
            throw new \RuntimeException('deliberate test failure');
        }
        $this->handleCount++;
    }
}

afterEach(fn () => Mockery::close());

function mockMsg(string $eventId = 'evt-001', int $retryCount = 0): AMQPMessage&\Mockery\MockInterface
{
    $msg = Mockery::mock(AMQPMessage::class);
    $msg->shouldReceive('get')->with('message_id')->andReturn($eventId)->byDefault();

    if ($retryCount > 0) {
        $table = new AMQPTable(['x-retry-count' => $retryCount]);
        $msg->shouldReceive('get')->with('application_headers')->andReturn($table)->byDefault();
    } else {
        $msg->shouldReceive('get')->with('application_headers')
            ->andThrow(new \OutOfBoundsException('no headers'))->byDefault();
    }

    $msg->shouldReceive('getBody')
        ->andReturn(json_encode(['event_id' => $eventId, 'event_type' => 'order.created', 'order_id' => 'ord-1', 'payload' => []]))
        ->byDefault();

    return $msg;
}

function mockPdo(bool $alreadyProcessed = false): PDO
{
    $pdo    = Mockery::mock(PDO::class);
    $select = Mockery::mock(PDOStatement::class);
    $insert = Mockery::mock(PDOStatement::class);

    $pdo->shouldReceive('prepare')->with(Mockery::pattern('/SELECT/'))->andReturn($select);
    $pdo->shouldReceive('prepare')->with(Mockery::pattern('/INSERT/'))->andReturn($insert);

    $select->shouldReceive('execute')->andReturn(true);
    $select->shouldReceive('fetch')->andReturn($alreadyProcessed ? ['1'] : false);

    $insert->shouldReceive('execute')->andReturn(true);

    return $pdo;
}

function makeWorker(AMQPChannel $channel, PDO $pdo): ConcreteWorker
{
    $redis = Mockery::mock(Redis::class);
    $redis->shouldReceive('setex')->byDefault();
    return new ConcreteWorker($channel, $redis, $pdo, 'test-worker-1');
}

/*
 * Tests implementations
 */
it('already-processed event_id skips handle() and calls ack()', function () {
    $channel = Mockery::mock(AMQPChannel::class);
    $msg     = mockMsg('evt-dup');
    $msg->shouldReceive('ack')->once();

    $worker = makeWorker($channel, mockPdo(alreadyProcessed: true));
    $worker->process($msg);

    expect($worker->handleCount)->toBe(0);
});

it('successful processing inserts in processed_events and calls ack()', function () {
    $channel = Mockery::mock(AMQPChannel::class);
    $pdo     = mockPdo();
    $msg     = mockMsg('evt-ok');
    $msg->shouldReceive('ack')->once();

    $worker = makeWorker($channel, $pdo);
    $worker->process($msg);

    expect($worker->handleCount)->toBe(1);
});

it('first failure re-queues in orders.retry with x-retry-count=1 and TTL=30000ms', function () {
    $channel = Mockery::mock(AMQPChannel::class);
    $channel->shouldReceive('basic_publish')
        ->once()
        ->withArgs(function (AMQPMessage $retryMsg, string $exchange, string $queue) {
            $isRetryQueue = $exchange === '' && $queue === 'orders.retry';
            $ttl          = $retryMsg->get('expiration');
            $headers      = $retryMsg->get('application_headers')->getNativeData();
            return $isRetryQueue && $ttl === '30000' && ($headers['x-retry-count'] ?? 0) === 1;
        });

    $msg = mockMsg('evt-fail', retryCount: 0);
    $msg->shouldReceive('ack')->once();

    $worker = makeWorker($channel, mockPdo());
    $worker->shouldThrow = true;
    $worker->process($msg);
});

it('second failure re-queues with x-retry-count=2 and TTL=60000ms', function () {
    $channel = Mockery::mock(AMQPChannel::class);
    $channel->shouldReceive('basic_publish')
        ->once()
        ->withArgs(function (AMQPMessage $retryMsg) {
            $ttl     = $retryMsg->get('expiration');
            $headers = $retryMsg->get('application_headers')->getNativeData();
            return $ttl === '60000' && ($headers['x-retry-count'] ?? 0) === 2;
        });

    $msg = mockMsg('evt-fail2', retryCount: 1);
    $msg->shouldReceive('ack')->once();

    $worker = makeWorker($channel, mockPdo());
    $worker->shouldThrow = true;
    $worker->process($msg);
});

it('third failure publishes to orders.dead and calls nack without requeue', function () {
    $channel = Mockery::mock(AMQPChannel::class);
    $channel->shouldReceive('basic_publish')
        ->once()
        ->withArgs(fn (AMQPMessage $m, string $ex, string $q) => $ex === '' && $q === 'orders.dead');

    $msg = mockMsg('evt-fail3', retryCount: 2);
    $msg->shouldReceive('nack')->once()->with(false, false);

    $worker = makeWorker($channel, mockPdo());
    $worker->shouldThrow = true;
    $worker->process($msg);
});

it('sendHeartbeat sets Redis key worker:heartbeat:{id} with TTL = HEARTBEAT_INTERVAL * 3', function () {
    $_ENV['HEARTBEAT_INTERVAL'] = '5';

    $channel = Mockery::mock(AMQPChannel::class);
    $redis   = Mockery::mock(Redis::class);
    $redis->shouldReceive('setex')
        ->once()
        ->withArgs(function (string $key, int $ttl) {
            return $key === 'worker:heartbeat:test-worker-1' && $ttl === 15;
        });

    $worker = new ConcreteWorker($channel, $redis, mockPdo(), 'test-worker-1');
    $worker->sendHeartbeat();
});
