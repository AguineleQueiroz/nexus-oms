<?php

use App\Exceptions\ValidationException;
use App\Http\Request;

it('fromGlobals populates method and uri from server globals', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/api/orders';

    $request = Request::fromGlobals();

    expect($request->getMethod())->toBe('POST')
        ->and($request->getUri())->toBe('/api/orders');
});

it('json parses raw JSON body', function () {
    $payload = ['name' => 'John', 'age' => 30];
    $request = Request::create('POST', '/test', json_encode($payload));

    expect($request->json())->toBe($payload);
});

it('json returns empty array for empty body', function () {
    $request = Request::create('POST', '/test', '');

    expect($request->json())->toBe([]);
});

it('get returns a query string value', function () {
    $request = Request::create('GET', '/test', '', ['status' => 'created']);

    expect($request->get('status'))->toBe('created');
});

it('get returns null for missing key', function () {
    expect(Request::create('GET', '/test')->get('missing'))->toBeNull();
});

it('validate throws ValidationException when a required field is missing', function () {
    $request = Request::create('POST', '/test', json_encode(['name' => 'John']));

    expect(fn() => $request->validate(['name' => 'required', 'email' => 'required']))
        ->toThrow(ValidationException::class);
});

it('validate does not throw when all required fields are present', function () {
    $request = Request::create('POST', '/test', json_encode(['name' => 'John', 'email' => 'j@test.com']));

    $request->validate(['name' => 'required', 'email' => 'required']);

    expect(true)->toBeTrue();
});
