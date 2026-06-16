<?php

use App\Events\EventFactory;
use App\Events\OrderEvent;

it('fromArray reconstructs an OrderEvent without data loss', function () {
    $original = OrderEvent::create('order.shipped', 'order-xyz', ['tracking' => 'BR123456789']);
    $array = $original->toArray();

    $reconstructed = EventFactory::fromArray($array);

    expect($reconstructed->eventId)->toBe($original->eventId)
        ->and($reconstructed->eventType)->toBe($original->eventType)
        ->and($reconstructed->orderId)->toBe($original->orderId)
        ->and($reconstructed->occurredAt)->toBe($original->occurredAt)
        ->and($reconstructed->payload)->toBe($original->payload);
});

it('fromArray throws InvalidArgumentException when event_id is missing', function () {
    expect(fn() => EventFactory::fromArray([
        'event_type' => 'order.created',
        'order_id' => 'order-uuid',
        'occurred_at' => '2025-01-01T00:00:00Z',
        'payload' => [],
    ]))->toThrow(InvalidArgumentException::class);
});

it('fromArray throws InvalidArgumentException when event_type is missing', function () {
    expect(fn() => EventFactory::fromArray([
        'event_id' => 'uuid-123',
        'order_id' => 'order-uuid',
        'occurred_at' => '2025-01-01T00:00:00Z',
        'payload' => [],
    ]))->toThrow(InvalidArgumentException::class);
});

it('fromArray throws InvalidArgumentException when order_id is missing', function () {
    expect(fn() => EventFactory::fromArray([
        'event_id' => 'uuid-123',
        'event_type' => 'order.created',
        'occurred_at' => '2025-01-01T00:00:00Z',
        'payload' => [],
    ]))->toThrow(InvalidArgumentException::class);
});
