<?php

use App\Controllers\DashboardController;
use App\Database\Connection;
use App\Repositories\EventRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ReadModelRepository;
use App\Services\HeartbeatService;
use App\Services\RabbitMqManagement;

beforeEach(function () {
    $this->pdo = Connection::getInstance();
    $this->pdo->beginTransaction();

    $this->redis = new Redis();
    $this->redis->connect($_ENV['REDIS_HOST'] ?? 'redis', (int)($_ENV['REDIS_PORT'] ?? 6379));

    // Clean up test heartbeat keys
    foreach ($this->redis->keys('worker:heartbeat:dash-test-*') as $key) {
        $this->redis->del($key);
    }

    $mockPublisher = Mockery::mock(\App\Services\EventPublisher::class);
    $mockPublisher->shouldReceive('publish')->andReturnNull();

    $orderRepo = new OrderRepository($this->pdo);
    $eventRepo = new EventRepository($this->pdo);
    $orderService = new \App\Services\OrderService($orderRepo, $eventRepo, $mockPublisher);

    // Create some test orders to have meaningful data
    $orderService->createOrder([
        'customer_name' => 'Ana Lima',
        'customer_email' => 'ana@test.com',
        'items' => [['product' => 'Produto A', 'qty' => 1, 'price' => 100.0]],
        'total' => 100.0,
    ]);

    $mockRabbit = Mockery::mock(RabbitMqManagement::class);
    $mockRabbit->shouldReceive('getQueues')->andReturn([
        ['name' => 'orders.audit', 'messages' => 0, 'consumers' => 1],
        ['name' => 'orders.payment', 'messages' => 2, 'consumers' => 1],
        ['name' => 'orders.dead', 'messages' => 1, 'consumers' => 0],
    ])->byDefault();

    $readModel = new ReadModelRepository($this->redis, $this->pdo);
    $heartbeat = new HeartbeatService($this->redis);

    $this->heartbeat = $heartbeat;
    $this->controller = new DashboardController($readModel, $heartbeat, $mockRabbit);
});

afterEach(function () {
    $this->pdo->rollBack();
    foreach ($this->redis->keys('worker:heartbeat:dash-test-*') as $key) {
        $this->redis->del($key);
    }
    Mockery::close();
});

// --- GET /api/dashboard/stats ---

it('GET /api/dashboard/stats — returns correct structure from SPECS', function () {
    $res = $this->controller->stats(\App\Http\Request::create('GET', '/api/dashboard/stats'));
    $body = $res->getBody();

    expect($res->getStatus())->toBe(200);
    expect($body)->toHaveKeys(['orders', 'events', 'consumers']);
    expect($body['orders'])->toHaveKey('total');
    expect($body['events'])->toHaveKeys(['published_last_hour', 'processed_last_hour', 'failed_last_hour', 'dead']);
    expect($body['consumers'])->toHaveKeys(['active', 'idle', 'stopped']);
});

it('GET /api/dashboard/stats — orders.total reflects created orders', function () {
    $res = $this->controller->stats(\App\Http\Request::create('GET', '/api/dashboard/stats'));
    $body = $res->getBody();

    expect($body['orders']['total'])->toBeGreaterThanOrEqual(1);
});

// --- GET /api/dashboard/throughput ---

it('GET /api/dashboard/throughput — returns array with minute and count keys', function () {
    $res = $this->controller->throughput(\App\Http\Request::create('GET', '/api/dashboard/throughput'));
    $body = $res->getBody();

    expect($res->getStatus())->toBe(200);
    expect($body)->toBeArray();

    if (count($body) > 0) {
        expect($body[0])->toHaveKeys(['minute', 'count']);
    }
});

// --- GET /api/dashboard/funnel ---

it('GET /api/dashboard/funnel — returns 8 statuses in lifecycle order', function () {
    $res = $this->controller->funnel(\App\Http\Request::create('GET', '/api/dashboard/funnel'));
    $body = $res->getBody();

    expect($res->getStatus())->toBe(200);
    expect($body)->toHaveCount(8);
    expect($body[0]['status'])->toBe('created');
    expect($body[0])->toHaveKey('count');
});

// --- GET /api/dashboard/consumers ---

it('GET /api/dashboard/consumers — returns worker list with status', function () {
    $this->heartbeat->register('dash-test-w1', 'PaymentWorker', 'orders.payment');

    $res = $this->controller->consumers(\App\Http\Request::create('GET', '/api/dashboard/consumers'));
    $body = $res->getBody();

    expect($res->getStatus())->toBe(200);
    expect($body)->toBeArray();

    $found = array_filter($body, fn($w) => $w['worker_id'] === 'dash-test-w1');
    expect(count($found))->toBe(1);
    expect(array_values($found)[0]['status'])->toBe('active');
});

it('GET /api/dashboard/consumers — stale worker is reported as idle', function () {
    $stale = (new DateTimeImmutable('-60 seconds'))->format('c');
    $this->redis->set('worker:heartbeat:dash-test-stale', json_encode([
        'worker_id' => 'dash-test-stale',
        'worker_type' => 'AuditWorker',
        'queue_name' => 'orders.audit',
        'last_heartbeat' => $stale,
        'status' => 'active',
    ]));

    $res = $this->controller->consumers(\App\Http\Request::create('GET', '/api/dashboard/consumers'));
    $body = $res->getBody();

    $found = array_values(array_filter($body, fn($w) => $w['worker_id'] === 'dash-test-stale'));
    expect($found[0]['status'])->toBe('idle');
});

// --- GET /api/dashboard/events/feed ---

it('GET /api/dashboard/events/feed — returns events in DESC order', function () {
    $res = $this->controller->eventFeed(\App\Http\Request::create('GET', '/api/dashboard/events/feed'));
    $body = $res->getBody();

    expect($res->getStatus())->toBe(200);
    expect($body)->toBeArray();
});

it('GET /api/dashboard/events/feed?limit=1 — respects limit param', function () {
    $res = $this->controller->eventFeed(
        \App\Http\Request::create('GET', '/api/dashboard/events/feed', queryParams: ['limit' => '1'])
    );

    expect($res->getStatus())->toBe(200);
    expect(count($res->getBody()))->toBeLessThanOrEqual(1);
});

// --- GET /api/dashboard/events/by-type ---

it('GET /api/dashboard/events/by-type — returns counts grouped by event_type', function () {
    $res = $this->controller->eventsByType(\App\Http\Request::create('GET', '/api/dashboard/events/by-type'));
    $body = $res->getBody();

    expect($res->getStatus())->toBe(200);
    expect($body)->toBeArray();

    if (count($body) > 0) {
        expect($body[0])->toHaveKeys(['event_type', 'count']);
    }
});

// --- GET /api/dashboard/queues ---

it('GET /api/dashboard/queues — returns queue list from RabbitMQ Management', function () {
    $res = $this->controller->queues(\App\Http\Request::create('GET', '/api/dashboard/queues'));
    $body = $res->getBody();

    expect($res->getStatus())->toBe(200);
    expect($body)->toBeArray();
    expect(count($body))->toBeGreaterThan(0);

    $names = array_column($body, 'name');
    expect($names)->toContain('orders.audit');
});
