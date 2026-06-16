<?php

use App\Controllers\OrderController;
use App\Database\Connection;
use App\Http\Request;
use App\Repositories\EventRepository;
use App\Repositories\OrderRepository;
use App\Services\EventPublisher;
use App\Services\OrderService;

beforeEach(function () {
    $this->pdo = Connection::getInstance();
    $this->pdo->beginTransaction();

    $mockPublisher = Mockery::mock(EventPublisher::class);
    $mockPublisher->shouldReceive('publish')->andReturnNull();

    $orderRepo = new OrderRepository($this->pdo);
    $eventRepo = new EventRepository($this->pdo);
    $orderService = new OrderService($orderRepo, $eventRepo, $mockPublisher);

    $this->controller = new OrderController($orderService, $orderRepo, $eventRepo);
});

afterEach(function () {
    $this->pdo->rollBack();
    Mockery::close();
});

function validOrderBody(array $override = []): string
{
    return json_encode(array_merge([
        'customer_name' => 'João Silva',
        'customer_email' => 'joao@exemplo.com',
        'items' => [
            ['product' => 'Tênis Nike Air', 'qty' => 1, 'price' => 459.90],
        ],
    ], $override));
}

// --- POST /api/orders ---

it('POST /api/orders — valid payload returns 201 with id and status', function () {
    $req = Request::create('POST', '/api/orders', validOrderBody());
    $res = $this->controller->create($req);

    expect($res->getStatus())->toBe(201);
    $body = $res->getBody();
    expect($body)->toHaveKey('id');
    expect($body['status'])->toBe('payment_pending');
});

it('POST /api/orders — same idempotency_key returns 200 with same order id', function () {
    $payload = validOrderBody(['idempotency_key' => 'idem-key-001']);
    $res1 = $this->controller->create(Request::create('POST', '/api/orders', $payload));
    $res2 = $this->controller->create(Request::create('POST', '/api/orders', $payload));

    expect($res1->getStatus())->toBe(201);
    expect($res2->getStatus())->toBe(200);
    expect($res1->getBody()['id'])->toBe($res2->getBody()['id']);
});

it('POST /api/orders — missing customer_name returns 422 with error key', function () {
    $req = Request::create('POST', '/api/orders', validOrderBody(['customer_name' => '']));
    $res = $this->controller->create($req);

    expect($res->getStatus())->toBe(422);
    expect($res->getBody())->toHaveKey('errors');
    expect($res->getBody()['errors'])->toHaveKey('customer_name');
});

it('POST /api/orders — missing items field returns 422', function () {
    $data = json_decode(validOrderBody(), true);
    unset($data['items']);
    $res = $this->controller->create(Request::create('POST', '/api/orders', json_encode($data)));

    expect($res->getStatus())->toBe(422);
});

it('POST /api/orders — empty items array returns 422', function () {
    $res = $this->controller->create(
        Request::create('POST', '/api/orders', validOrderBody(['items' => []]))
    );

    expect($res->getStatus())->toBe(422);
});

// --- GET /api/orders ---

it('GET /api/orders — returns 200 with data array and meta', function () {
    $this->controller->create(Request::create('POST', '/api/orders', validOrderBody()));

    $res = $this->controller->index(Request::create('GET', '/api/orders'));

    expect($res->getStatus())->toBe(200);
    $body = $res->getBody();
    expect($body)->toHaveKeys(['data', 'meta']);
    expect($body['meta'])->toHaveKey('total');
    expect($body['data'])->toBeArray();
});

it('GET /api/orders — ?status filter returns only matching orders', function () {
    $this->controller->create(Request::create('POST', '/api/orders', validOrderBody()));

    $res = $this->controller->index(
        Request::create('GET', '/api/orders', queryParams: ['status' => 'payment_pending'])
    );

    expect($res->getStatus())->toBe(200);
    foreach ($res->getBody()['data'] as $order) {
        expect($order['status'])->toBe('payment_pending');
    }
});

it('GET /api/orders — pagination meta reflects per_page param', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->controller->create(
            Request::create('POST', '/api/orders', validOrderBody(['customer_email' => "u{$i}@test.com"]))
        );
    }

    $res = $this->controller->index(
        Request::create('GET', '/api/orders', queryParams: ['page' => '1', 'per_page' => '2'])
    );

    expect($res->getStatus())->toBe(200);
    expect($res->getBody()['meta']['per_page'])->toBe(2);
    expect(count($res->getBody()['data']))->toBeLessThanOrEqual(2);
});

// --- GET /api/orders/{id} ---

it('GET /api/orders/{id} — returns order with events array', function () {
    $id = $this->controller->create(Request::create('POST', '/api/orders', validOrderBody()))->getBody()['id'];

    $res = $this->controller->show(Request::create('GET', "/api/orders/{$id}"), $id);

    expect($res->getStatus())->toBe(200);
    expect($res->getBody())->toHaveKey('events');
    expect($res->getBody()['events'])->toBeArray();
});

it('GET /api/orders/{id} — unknown UUID returns 404', function () {
    $id = '00000000-0000-0000-0000-000000000000';

    $res = $this->controller->show(Request::create('GET', "/api/orders/{$id}"), $id);

    expect($res->getStatus())->toBe(404);
});

it('GET /api/orders/{id} — malformed UUID returns 400', function () {
    $res = $this->controller->show(Request::create('GET', '/api/orders/not-a-uuid'), 'not-a-uuid');

    expect($res->getStatus())->toBe(400);
});

// --- POST /{id}/pay ---

it('POST /{id}/pay — transitions order to paid and returns 200', function () {
    $id = $this->controller->create(Request::create('POST', '/api/orders', validOrderBody()))->getBody()['id'];

    $res = $this->controller->pay(Request::create('POST', "/api/orders/{$id}/pay"), $id);

    expect($res->getStatus())->toBe(200);
    expect($res->getBody()['status'])->toBe('paid');
});

it('POST /{id}/pay — wrong status returns 409', function () {
    $id = $this->controller->create(Request::create('POST', '/api/orders', validOrderBody()))->getBody()['id'];
    $this->controller->pay(Request::create('POST', "/api/orders/{$id}/pay"), $id);

    $res = $this->controller->pay(Request::create('POST', "/api/orders/{$id}/pay"), $id);

    expect($res->getStatus())->toBe(409);
});

// --- POST /{id}/refuse-payment ---

it('POST /{id}/refuse-payment — transitions to payment_refused', function () {
    $id = $this->controller->create(Request::create('POST', '/api/orders', validOrderBody()))->getBody()['id'];

    $res = $this->controller->refusePayment(Request::create('POST', "/api/orders/{$id}/refuse-payment"), $id);

    expect($res->getStatus())->toBe(200);
    expect($res->getBody()['status'])->toBe('payment_refused');
});

// --- POST /{id}/cancel ---

it('POST /{id}/cancel — cancels paid order and returns 200', function () {
    $id = $this->controller->create(Request::create('POST', '/api/orders', validOrderBody()))->getBody()['id'];
    $this->controller->pay(Request::create('POST', "/api/orders/{$id}/pay"), $id);

    $res = $this->controller->cancel(Request::create('POST', "/api/orders/{$id}/cancel"), $id);

    expect($res->getStatus())->toBe(200);
    expect($res->getBody()['status'])->toBe('cancelled');
});

it('POST /{id}/cancel — returns 409 when order is shipped', function () {
    $id = $this->controller->create(Request::create('POST', '/api/orders', validOrderBody()))->getBody()['id'];
    $this->controller->pay(Request::create('POST', "/api/orders/{$id}/pay"), $id);
    $this->controller->advance(Request::create('POST', "/api/orders/{$id}/advance"), $id); // picking
    $this->controller->advance(Request::create('POST', "/api/orders/{$id}/advance"), $id); // shipped

    $res = $this->controller->cancel(Request::create('POST', "/api/orders/{$id}/cancel"), $id);

    expect($res->getStatus())->toBe(409);
});

// --- POST /{id}/advance ---

it('POST /{id}/advance — advances paid order to picking', function () {
    $id = $this->controller->create(Request::create('POST', '/api/orders', validOrderBody()))->getBody()['id'];
    $this->controller->pay(Request::create('POST', "/api/orders/{$id}/pay"), $id);

    $res = $this->controller->advance(Request::create('POST', "/api/orders/{$id}/advance"), $id);

    expect($res->getStatus())->toBe(200);
    expect($res->getBody()['status'])->toBe('picking');
});
