<?php

use App\Database\Connection;
use App\Events\OrderEvent;
use App\Repositories\EventRepository;
use App\Repositories\OrderRepository;

beforeEach(function () {
    Connection::getInstance()->beginTransaction();
    $pdo = Connection::getInstance();
    $this->repo = new EventRepository($pdo);
    $this->orderRepo = new OrderRepository($pdo);

    $order = $this->orderRepo->save([
        'customer_name' => 'Test User',
        'customer_email' => 'test@example.com',
        'items' => [['product' => 'Item', 'qty' => 1, 'price' => 10.0]],
        'total' => 10.0,
        'idempotency_key' => null,
    ]);
    $this->orderId = $order['id'];
});

afterEach(function () {
    Connection::getInstance()->rollBack();
});

it('save inserts the event into order_events', function () {
    $event = OrderEvent::create('order.created', $this->orderId, ['total' => 10.0]);

    $this->repo->save($event, 'order.created');

    $events = $this->repo->findByOrderId($this->orderId);
    expect($events)->toHaveCount(1)
        ->and($events[0]['event_type'])->toBe('order.created');
});

it('findByOrderId returns events in published_at ASC order', function () {
    $this->repo->save(OrderEvent::create('order.created', $this->orderId, []), 'order.created');
    $this->repo->save(OrderEvent::create('order.payment.pending', $this->orderId, []), 'order.payment.pending');
    $this->repo->save(OrderEvent::create('order.payment.approved', $this->orderId, []), 'order.payment.approved');

    $events = $this->repo->findByOrderId($this->orderId);

    expect($events[0]['event_type'])->toBe('order.created')
        ->and($events[1]['event_type'])->toBe('order.payment.pending')
        ->and($events[2]['event_type'])->toBe('order.payment.approved');
});

it('findByOrderId returns empty array when order has no events', function () {
    $result = $this->repo->findByOrderId($this->orderId);

    expect($result)->toBe([]);
});

it('markProcessed sets processed to true', function () {
    $event = OrderEvent::create('order.shipped', $this->orderId, []);
    $this->repo->save($event, 'order.shipped');

    $this->repo->markProcessed($event->eventId, 'tracking-worker-1');

    $events = $this->repo->findByOrderId($this->orderId);
    expect($events[0]['processed'])->toBeTrue()
        ->and($events[0]['worker_id'])->toBe('tracking-worker-1');
});

it('markProcessed sets processed_at timestamp', function () {
    $event = OrderEvent::create('order.delivered', $this->orderId, []);
    $this->repo->save($event, 'order.delivered');

    $this->repo->markProcessed($event->eventId, 'audit-worker-1');

    $events = $this->repo->findByOrderId($this->orderId);
    expect($events[0]['processed_at'])->not->toBeNull();
});
