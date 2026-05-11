<?php

namespace App\Services;

use App\Events\OrderEvent;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\OrderNotFoundException;
use App\Repositories\EventRepository;
use App\Repositories\OrderRepository;
use Random\RandomException;

class OrderService
{
    private const TRANSITIONS = [
        'paid'    => 'picking',
        'picking' => 'shipped',
        'shipped' => 'delivered',
    ];

    private const CANCELLABLE = ['created', 'payment_pending', 'paid', 'picking'];

    public function __construct(
        private readonly OrderRepository $orderRepo,
        private readonly EventRepository $eventRepo,
        private readonly EventPublisher  $publisher,
    ) {}

    public function createOrder(array $data): array
    {
        if (!empty($data['idempotency_key'])) {
            $existing = $this->orderRepo->findByIdempotencyKey($data['idempotency_key']);
            if ($existing !== null) {
                return $existing;
            }
        }

        $order = $this->orderRepo->save($data);

        $this->publishAndRecord($order, 'order.created');

        $this->orderRepo->updateStatus($order['id'], 'payment_pending');
        $order['status'] = 'payment_pending';

        $this->publishAndRecord($order, 'order.payment.pending');

        return $order;
    }

    public function approvePayment(string $orderId): array
    {
        $order = $this->fetchOrFail($orderId);
        $this->assertStatus($order, 'payment_pending', 'approve payment');

        $this->orderRepo->updateStatus($orderId, 'paid');
        $order['status'] = 'paid';

        $this->publishAndRecord($order, 'order.payment.approved');

        return $order;
    }

    public function refusePayment(string $orderId): array
    {
        $order = $this->fetchOrFail($orderId);
        $this->assertStatus($order, 'payment_pending', 'refuse payment');

        $this->orderRepo->updateStatus($orderId, 'payment_refused');
        $order['status'] = 'payment_refused';

        $this->publishAndRecord($order, 'order.payment.refused');

        return $order;
    }

    public function cancel(string $orderId): array
    {
        $order = $this->fetchOrFail($orderId);

        if (!in_array($order['status'], self::CANCELLABLE, true)) {
            throw new InvalidTransitionException(
                "Order #{$orderId} cannot be cancelled from status '{$order['status']}'."
            );
        }

        $this->orderRepo->updateStatus($orderId, 'cancelled');
        $order['status'] = 'cancelled';

        $this->publishAndRecord($order, 'order.cancelled');

        return $order;
    }

    public function advance(string $orderId): array
    {
        $order  = $this->fetchOrFail($orderId);
        $status = $order['status'];

        if (!isset(self::TRANSITIONS[$status])) {
            throw new InvalidTransitionException(
                "Order #{$orderId} cannot be advanced from status '{$status}'."
            );
        }

        $next = self::TRANSITIONS[$status];
        $this->orderRepo->updateStatus($orderId, $next);
        $order['status'] = $next;

        $eventTypeMap = [
            'picking'   => 'order.picking',
            'shipped'   => 'order.shipped',
            'delivered' => 'order.delivered',
        ];

        $this->publishAndRecord($order, $eventTypeMap[$next]);

        return $order;
    }

    private function fetchOrFail(string $orderId): array
    {
        $order = $this->orderRepo->findById($orderId);
        if ($order === null) {
            throw new OrderNotFoundException("Order #{$orderId} not found.");
        }
        return $order;
    }

    private function assertStatus(array $order, string $expected, string $action): void
    {
        if ($order['status'] !== $expected) {
            throw new InvalidTransitionException(
                "Cannot {$action} for order #{$order['id']} in status '{$order['status']}'."
            );
        }
    }

    /**
     * @throws RandomException
     */
    private function publishAndRecord(array $order, string $eventType): void
    {
        $payload = [
            'customer_name'  => $order['customer_name'],
            'customer_email' => $order['customer_email'],
            'total'          => $order['total'],
            'status'         => $order['status'],
        ];

        $event = OrderEvent::create($eventType, $order['id'], $payload);

        $this->eventRepo->save($event, $eventType);
        $this->publisher->publish($event);
    }
}
