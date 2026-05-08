<?php

use App\Http\Response;

it('json sets Content-Type application/json', function () {
    $response = Response::json(['key' => 'value'], 200);

    expect($response->getHeaders()['Content-Type'])->toBe('application/json');
});

it('json sets the correct status code', function () {
    expect(Response::json([], 404)->getStatus())->toBe(404);
});

it('send outputs JSON-encoded body', function () {
    $response = Response::json(['key' => 'value'], 200);

    ob_start();
    $response->send();
    $output = ob_get_clean();

    expect($output)->toBe(json_encode(['key' => 'value']));
});
