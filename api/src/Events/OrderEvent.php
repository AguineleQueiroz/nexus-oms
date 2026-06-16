<?php

namespace App\Events;

use InvalidArgumentException;
use Random\RandomException;

final class OrderEvent
{
    private const VALID_TYPES = [
        'order.created',
        'order.payment.pending',
        'order.payment.approved',
        'order.payment.refused',
        'order.picking',
        'order.shipped',
        'order.delivered',
        'order.cancelled',
    ];

    private function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly string $orderId,
        public readonly string $occurredAt,
        public readonly array  $payload,
    )
    {
    }

    /**
     * @throws RandomException
     */
    public static function create(string $eventType, string $orderId, array $payload): self
    {
        if (!in_array($eventType, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException("Invalid event type: {$eventType}");
        }

        return new self(
            eventId: self::generateUuid(),
            eventType: $eventType,
            orderId: $orderId,
            occurredAt: gmdate('Y-m-d\TH:i:s\Z'),
            payload: $payload,
        );
    }

    /**
     * @throws RandomException
     */
    private static function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public static function restore(
        string $eventId,
        string $eventType,
        string $orderId,
        string $occurredAt,
        array  $payload,
    ): self
    {
        return new self($eventId, $eventType, $orderId, $occurredAt, $payload);
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'order_id' => $this->orderId,
            'occurred_at' => $this->occurredAt,
            'payload' => $this->payload,
        ];
    }
}
