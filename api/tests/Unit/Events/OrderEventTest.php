<?php

use App\Events\OrderEvent;

it('create generates a UUID event_id', function () {
    $event = OrderEvent::create('order.created', 'order-uuid-123', ['total' => 100.0]);

    expect($event->eventId)
        ->toBeString()
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
});

it('create sets occurred_at as ISO-8601', function () {
    $event = OrderEvent::create('order.created', 'order-uuid-123', ['total' => 100.0]);

    expect($event->occurredAt)
        ->toBeString()
        ->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z$/');
});

it('create stores event_type, order_id and payload', function () {
    $payload = ['customer_name' => 'João Silva', 'total' => 549.60];
    $event   = OrderEvent::create('order.payment.approved', 'order-abc', $payload);

    expect($event->eventType)->toBe('order.payment.approved')
        ->and($event->orderId)->toBe('order-abc')
        ->and($event->payload)->toBe($payload);
});

it('toArray produces the exact structure defined in SPECS', function () {
    $payload = ['customer_name' => 'João', 'total' => 100.0];
    $event   = OrderEvent::create('order.created', 'order-uuid-123', $payload);
    $array   = $event->toArray();

    expect($array)->toHaveKeys(['event_id', 'event_type', 'order_id', 'occurred_at', 'payload'])
        ->and($array['event_type'])->toBe('order.created')
        ->and($array['order_id'])->toBe('order-uuid-123')
        ->and($array['payload'])->toBe($payload);
});

it('throws InvalidArgumentException for unknown event_type', function () {
    expect(fn () => OrderEvent::create('order.unknown_type', 'order-uuid', []))
        ->toThrow(InvalidArgumentException::class);
});

it('is immutable — properties cannot be changed after creation', function () {
    $event = OrderEvent::create('order.created', 'order-uuid-123', []);

    expect(fn () => $event->eventId = 'changed')
        ->toThrow(Error::class);
});
