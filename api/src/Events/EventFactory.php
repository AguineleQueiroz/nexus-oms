<?php

namespace App\Events;

use InvalidArgumentException;

final class EventFactory
{
    public static function fromArray(array $data): OrderEvent
    {
        foreach (['event_id', 'event_type', 'order_id', 'occurred_at', 'payload'] as $key) {
            if (!isset($data[$key])) {
                throw new InvalidArgumentException("Missing required field: {$key}");
            }
        }

        return OrderEvent::restore(
            eventId: $data['event_id'],
            eventType: $data['event_type'],
            orderId: $data['order_id'],
            occurredAt: $data['occurred_at'],
            payload: $data['payload'],
        );
    }
}
