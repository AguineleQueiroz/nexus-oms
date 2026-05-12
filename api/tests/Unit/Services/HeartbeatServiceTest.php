<?php

use App\Services\HeartbeatService;

afterEach(fn () => Mockery::close());

it('register() sets worker heartbeat in Redis with correct JSON structure', function () {
    $redis = Mockery::mock(Redis::class);
    $redis->shouldReceive('set')
        ->once()
        ->withArgs(function (string $key, string $value) {
            $data = json_decode($value, true);
            return $key === 'worker:heartbeat:pay-1'
                && $data['worker_id']   === 'pay-1'
                && $data['worker_type'] === 'PaymentWorker'
                && $data['queue_name']  === 'orders.payment'
                && $data['status']      === 'active'
                && isset($data['last_heartbeat'], $data['started_at']);
        });

    (new HeartbeatService($redis))->register('pay-1', 'PaymentWorker', 'orders.payment');
});

it('getAll() returns all workers found under worker:heartbeat:* keys', function () {
    $now  = (new DateTimeImmutable())->format('c');
    $blob = json_encode(['worker_id' => 'w1', 'last_heartbeat' => $now, 'status' => 'active']);

    $redis = Mockery::mock(Redis::class);
    $redis->shouldReceive('keys')->with('worker:heartbeat:*')->andReturn(['worker:heartbeat:w1']);
    $redis->shouldReceive('get')->with('worker:heartbeat:w1')->andReturn($blob);

    $workers = (new HeartbeatService($redis))->getAll();

    expect($workers)->toHaveCount(1);
    expect($workers[0]['worker_id'])->toBe('w1');
});

it('getStatus() returns idle when last_heartbeat is older than HEARTBEAT_INTERVAL * 3 seconds', function () {
    $_ENV['HEARTBEAT_INTERVAL'] = '5';
    $stale = (new DateTimeImmutable('-60 seconds'))->format('c');

    $redis = Mockery::mock(Redis::class);
    $redis->shouldReceive('get')
        ->with('worker:heartbeat:worker-1')
        ->andReturn(json_encode(['last_heartbeat' => $stale]));

    expect((new HeartbeatService($redis))->getStatus('worker-1'))->toBe('idle');
});

it('getStatus() returns active when last_heartbeat is recent', function () {
    $_ENV['HEARTBEAT_INTERVAL'] = '5';
    $recent = (new DateTimeImmutable('-2 seconds'))->format('c');

    $redis = Mockery::mock(Redis::class);
    $redis->shouldReceive('get')
        ->with('worker:heartbeat:worker-2')
        ->andReturn(json_encode(['last_heartbeat' => $recent]));

    expect((new HeartbeatService($redis))->getStatus('worker-2'))->toBe('active');
});

it('getStatus() returns stopped when key does not exist in Redis', function () {
    $redis = Mockery::mock(Redis::class);
    $redis->shouldReceive('get')->with('worker:heartbeat:ghost')->andReturn(false);

    expect((new HeartbeatService($redis))->getStatus('ghost'))->toBe('stopped');
});
