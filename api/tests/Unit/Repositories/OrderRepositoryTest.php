<?php

use App\Database\Connection;
use App\Exceptions\DuplicateOrderException;
use App\Repositories\OrderRepository;

beforeEach(function () {
    Connection::getInstance()->beginTransaction();
    $this->repo = new OrderRepository(Connection::getInstance());
});

afterEach(function () {
    Connection::getInstance()->rollBack();
});

function sampleOrderData(array $overrides = []): array
{
    return array_merge([
        'customer_name'   => 'João Silva',
        'customer_email'  => 'joao@exemplo.com',
        'items'           => [['product' => 'Tênis', 'qty' => 1, 'price' => 100.0]],
        'total'           => 100.0,
        'idempotency_key' => null,
    ], $overrides);
}

it('save inserts an order and returns it with a UUID id', function () {
    $order = $this->repo->save(sampleOrderData());

    expect($order['id'])
        ->toBeString()
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i')
        ->and($order['status'])->toBe('created')
        ->and($order['customer_name'])->toBe('João Silva');
});

it('findById returns the order when it exists', function () {
    $saved = $this->repo->save(sampleOrderData());
    $found = $this->repo->findById($saved['id']);

    expect($found)->not->toBeNull()
        ->and($found['id'])->toBe($saved['id']);
});

it('findById returns null for a non-existent UUID', function () {
    $result = $this->repo->findById('00000000-0000-0000-0000-000000000000');

    expect($result)->toBeNull();
});

it('findAll returns all orders when no filter is applied', function () {
    $this->repo->save(sampleOrderData(['idempotency_key' => 'key-1']));
    $this->repo->save(sampleOrderData(['idempotency_key' => 'key-2']));

    $result = $this->repo->findAll();

    expect(count($result['data']))->toBeGreaterThanOrEqual(2);
});

it('findAll filters by status', function () {
    $order = $this->repo->save(sampleOrderData());
    $this->repo->updateStatus($order['id'], 'paid');

    $result = $this->repo->findAll(['status' => 'paid']);

    $ids = array_column($result['data'], 'id');
    expect(in_array($order['id'], $ids, true))->toBeTrue();
});

it('findAll with status filter excludes orders with different status', function () {
    $order = $this->repo->save(sampleOrderData());

    $result = $this->repo->findAll(['status' => 'shipped']);

    $ids = array_column($result['data'], 'id');
    expect(in_array($order['id'], $ids, true))->toBeFalse();
});

it('findAll paginates correctly', function () {
    foreach (range(1, 5) as $i) {
        $this->repo->save(sampleOrderData(['idempotency_key' => "paginate-key-{$i}"]));
    }

    $result = $this->repo->findAll([], page: 1, perPage: 2);

    expect(count($result['data']))->toBeLessThanOrEqual(2)
        ->and($result['meta'])->toHaveKeys(['page', 'per_page', 'total']);
});

it('save throws DuplicateOrderException when idempotency_key already exists', function () {
    $this->repo->save(sampleOrderData(['idempotency_key' => 'duplicate-key']));

    expect(fn () => $this->repo->save(sampleOrderData(['idempotency_key' => 'duplicate-key'])))
        ->toThrow(DuplicateOrderException::class);
});

it('updateStatus changes the order status', function () {
    $order = $this->repo->save(sampleOrderData());

    $this->repo->updateStatus($order['id'], 'paid');

    $updated = $this->repo->findById($order['id']);
    expect($updated['status'])->toBe('paid');
});

it('updateStatus updates the updated_at timestamp', function () {
    $order = $this->repo->save(sampleOrderData());
    sleep(1);

    $this->repo->updateStatus($order['id'], 'picking');

    $updated = $this->repo->findById($order['id']);
    expect($updated['updated_at'])->not->toBe($order['created_at']);
});
