<?php

use App\Events\OrderEvent;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\OrderNotFoundException;
use App\Repositories\EventRepository;
use App\Repositories\OrderRepository;
use App\Services\EventPublisher;
use App\Services\OrderService;
use Mockery\MockInterface;

afterEach(fn() => Mockery::close());

function mockOrder(array $overrides = []): array
{
    return array_merge([
        'id' => 'order-uuid-123',
        'customer_name' => 'João Silva',
        'customer_email' => 'joao@exemplo.com',
        'items' => [['product' => 'Tênis', 'qty' => 1, 'price' => 100.0]],
        'total' => 100.0,
        'status' => 'created',
        'metadata' => [],
        'created_at' => '2025-01-01T00:00:00Z',
        'updated_at' => '2025-01-01T00:00:00Z',
        'idempotency_key' => null,
    ], $overrides);
}

function makeService(MockInterface $orderRepo, MockInterface $eventRepo, MockInterface $publisher): OrderService
{
    return new OrderService($orderRepo, $eventRepo, $publisher);
}

it('createOrder saves the order and publishes order.created and order.payment.pending', function () {
    /** @var OrderRepository&MockInterface $orderRepo */
    $orderRepo = Mockery::mock(OrderRepository::class);
    /** @var EventRepository&MockInterface $eventRepo */
    $eventRepo = Mockery::mock(EventRepository::class);
    /** @var EventPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(EventPublisher::class);

    $input = ['customer_name' => 'João', 'customer_email' => 'j@e.com', 'items' => [], 'total' => 0.0];

    $orderRepo->shouldReceive('findByIdempotencyKey')->never();
    $orderRepo->shouldReceive('save')->once()->andReturn(mockOrder(['status' => 'payment_pending']));
    $orderRepo->shouldReceive('updateStatus')->once()->with('order-uuid-123', 'payment_pending');
    $eventRepo->shouldReceive('save')->twice();
    $publisher->shouldReceive('publish')
        ->twice()
        ->withArgs(fn(OrderEvent $e) => in_array($e->eventType, ['order.created', 'order.payment.pending'], true));

    $result = makeService($orderRepo, $eventRepo, $publisher)->createOrder($input);

    expect($result['status'])->toBe('payment_pending');
});

it('createOrder with existing idempotency_key returns the existing order without side effects', function () {
    /** @var OrderRepository&MockInterface $orderRepo */
    $orderRepo = Mockery::mock(OrderRepository::class);
    /** @var EventRepository&MockInterface $eventRepo */
    $eventRepo = Mockery::mock(EventRepository::class);
    /** @var EventPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(EventPublisher::class);

    $existing = mockOrder(['status' => 'payment_pending', 'idempotency_key' => 'idem-key-1']);
    $orderRepo->shouldReceive('findByIdempotencyKey')->once()->with('idem-key-1')->andReturn($existing);
    $orderRepo->shouldReceive('save')->never();
    $publisher->shouldReceive('publish')->never();

    $input = ['customer_name' => 'João', 'customer_email' => 'j@e.com', 'items' => [], 'total' => 0.0, 'idempotency_key' => 'idem-key-1'];

    $result = makeService($orderRepo, $eventRepo, $publisher)->createOrder($input);

    expect($result)->toBe($existing);
});

it('approvePayment transitions payment_pending to paid and publishes order.payment.approved', function () {
    /** @var OrderRepository&MockInterface $orderRepo */
    $orderRepo = Mockery::mock(OrderRepository::class);
    /** @var EventRepository&MockInterface $eventRepo */
    $eventRepo = Mockery::mock(EventRepository::class);
    /** @var EventPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(EventPublisher::class);

    $orderRepo->shouldReceive('findById')->with('order-uuid-123')->andReturn(mockOrder(['status' => 'payment_pending']));
    $orderRepo->shouldReceive('updateStatus')->once()->with('order-uuid-123', 'paid');
    $eventRepo->shouldReceive('save')->once();
    $publisher->shouldReceive('publish')->once()->withArgs(fn(OrderEvent $e) => $e->eventType === 'order.payment.approved');

    makeService($orderRepo, $eventRepo, $publisher)->approvePayment('order-uuid-123');
});

it('approvePayment throws InvalidTransitionException if status is not payment_pending', function () {
    /** @var OrderRepository&MockInterface $orderRepo */
    $orderRepo = Mockery::mock(OrderRepository::class);
    $orderRepo->shouldReceive('findById')->andReturn(mockOrder(['status' => 'paid']));

    expect(fn() => makeService($orderRepo, Mockery::mock(EventRepository::class), Mockery::mock(EventPublisher::class))
        ->approvePayment('order-uuid-123'))
        ->toThrow(InvalidTransitionException::class);
});

it('refusePayment transitions payment_pending to payment_refused and publishes order.payment.refused', function () {
    /** @var OrderRepository&MockInterface $orderRepo */
    $orderRepo = Mockery::mock(OrderRepository::class);
    /** @var EventRepository&MockInterface $eventRepo */
    $eventRepo = Mockery::mock(EventRepository::class);
    /** @var EventPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(EventPublisher::class);

    $orderRepo->shouldReceive('findById')->andReturn(mockOrder(['status' => 'payment_pending']));
    $orderRepo->shouldReceive('updateStatus')->once()->with('order-uuid-123', 'payment_refused');
    $eventRepo->shouldReceive('save')->once();
    $publisher->shouldReceive('publish')->once()->withArgs(fn(OrderEvent $e) => $e->eventType === 'order.payment.refused');

    makeService($orderRepo, $eventRepo, $publisher)->refusePayment('order-uuid-123');
});

it('cancel transitions paid order to cancelled', function () {
    /** @var OrderRepository&MockInterface $orderRepo */
    $orderRepo = Mockery::mock(OrderRepository::class);
    /** @var EventRepository&MockInterface $eventRepo */
    $eventRepo = Mockery::mock(EventRepository::class);
    /** @var EventPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(EventPublisher::class);

    $orderRepo->shouldReceive('findById')->andReturn(mockOrder(['status' => 'paid']));
    $orderRepo->shouldReceive('updateStatus')->once()->with('order-uuid-123', 'cancelled');
    $eventRepo->shouldReceive('save')->once();
    $publisher->shouldReceive('publish')->once()->withArgs(fn(OrderEvent $e) => $e->eventType === 'order.cancelled');

    makeService($orderRepo, $eventRepo, $publisher)->cancel('order-uuid-123');
});

it('cancel throws InvalidTransitionException when order is already shipped', function () {
    /** @var OrderRepository&MockInterface $orderRepo */
    $orderRepo = Mockery::mock(OrderRepository::class);
    $orderRepo->shouldReceive('findById')->andReturn(mockOrder(['status' => 'shipped']));

    expect(fn() => makeService($orderRepo, Mockery::mock(EventRepository::class), Mockery::mock(EventPublisher::class))
        ->cancel('order-uuid-123'))
        ->toThrow(InvalidTransitionException::class);
});

it('cancel throws InvalidTransitionException when order is delivered', function () {
    /** @var OrderRepository&MockInterface $orderRepo */
    $orderRepo = Mockery::mock(OrderRepository::class);
    $orderRepo->shouldReceive('findById')->andReturn(mockOrder(['status' => 'delivered']));

    expect(fn() => makeService($orderRepo, Mockery::mock(EventRepository::class), Mockery::mock(EventPublisher::class))
        ->cancel('order-uuid-123'))
        ->toThrow(InvalidTransitionException::class);
});

it('advance transitions paid to picking', function () {
    /** @var OrderRepository&MockInterface $orderRepo */
    $orderRepo = Mockery::mock(OrderRepository::class);
    /** @var EventRepository&MockInterface $eventRepo */
    $eventRepo = Mockery::mock(EventRepository::class);
    /** @var EventPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(EventPublisher::class);

    $orderRepo->shouldReceive('findById')->andReturn(mockOrder(['status' => 'paid']));
    $orderRepo->shouldReceive('updateStatus')->once()->with('order-uuid-123', 'picking');
    $eventRepo->shouldReceive('save')->once();
    $publisher->shouldReceive('publish')->once()->withArgs(fn(OrderEvent $e) => $e->eventType === 'order.picking');

    makeService($orderRepo, $eventRepo, $publisher)->advance('order-uuid-123');
});

it('advance transitions picking to shipped', function () {
    /** @var OrderRepository&MockInterface $orderRepo */
    $orderRepo = Mockery::mock(OrderRepository::class);
    /** @var EventRepository&MockInterface $eventRepo */
    $eventRepo = Mockery::mock(EventRepository::class);
    /** @var EventPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(EventPublisher::class);

    $orderRepo->shouldReceive('findById')->andReturn(mockOrder(['status' => 'picking']));
    $orderRepo->shouldReceive('updateStatus')->once()->with('order-uuid-123', 'shipped');
    $eventRepo->shouldReceive('save')->once();
    $publisher->shouldReceive('publish')->once()->withArgs(fn(OrderEvent $e) => $e->eventType === 'order.shipped');

    makeService($orderRepo, $eventRepo, $publisher)->advance('order-uuid-123');
});

it('advance transitions shipped to delivered', function () {
    /** @var OrderRepository&MockInterface $orderRepo */
    $orderRepo = Mockery::mock(OrderRepository::class);
    /** @var EventRepository&MockInterface $eventRepo */
    $eventRepo = Mockery::mock(EventRepository::class);
    /** @var EventPublisher&MockInterface $publisher */
    $publisher = Mockery::mock(EventPublisher::class);

    $orderRepo->shouldReceive('findById')->andReturn(mockOrder(['status' => 'shipped']));
    $orderRepo->shouldReceive('updateStatus')->once()->with('order-uuid-123', 'delivered');
    $eventRepo->shouldReceive('save')->once();
    $publisher->shouldReceive('publish')->once()->withArgs(fn(OrderEvent $e) => $e->eventType === 'order.delivered');

    makeService($orderRepo, $eventRepo, $publisher)->advance('order-uuid-123');
});

it('advance throws InvalidTransitionException when order is already delivered', function () {
    /** @var OrderRepository&MockInterface $orderRepo */
    $orderRepo = Mockery::mock(OrderRepository::class);
    $orderRepo->shouldReceive('findById')->andReturn(mockOrder(['status' => 'delivered']));

    expect(fn() => makeService($orderRepo, Mockery::mock(EventRepository::class), Mockery::mock(EventPublisher::class))
        ->advance('order-uuid-123'))
        ->toThrow(InvalidTransitionException::class);
});

it('throws OrderNotFoundException when order does not exist', function () {
    /** @var OrderRepository&MockInterface $orderRepo */
    $orderRepo = Mockery::mock(OrderRepository::class);
    $orderRepo->shouldReceive('findById')->andReturn(null);

    expect(fn() => makeService($orderRepo, Mockery::mock(EventRepository::class), Mockery::mock(EventPublisher::class))
        ->approvePayment('non-existent-id'))
        ->toThrow(OrderNotFoundException::class);
});
