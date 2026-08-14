<?php

declare(strict_types=1);

namespace Tests\Unit\Server\Client;

use Le0daniel\PhpTsBindings\Server\Client\OperationSPAClient;
use Le0daniel\PhpTsBindings\Server\Data\Toast;
use Le0daniel\PhpTsBindings\Server\Data\ToastType;
use Tests\Mocks\InvalidationNamespace;
use Tests\Mocks\ResultEnum;

test('a client that was never touched emits no directives at all', function () {
    expect(new OperationSPAClient()->serializeToArray())->toBeNull();
});

test('a toast is serialized as its type value and message', function () {
    $client = new OperationSPAClient();
    $client->toast(new Toast(ToastType::INFO, 'Heads up'));

    expect($client->serializeToArray())->toBe([
        'toasts' => [
            ['type' => 'info', 'message' => 'Heads up'],
        ],
        'type' => 'operations-spa',
    ]);
});

test('each toast helper emits a toast of its own type', function (string $method, string $expectedType) {
    $client = new OperationSPAClient();
    $client->{$method}('Message');

    expect($client->serializeToArray()['toasts'])->toBe([
        ['type' => $expectedType, 'message' => 'Message'],
    ]);
})->with([
    'success' => ['success', 'success'],
    'error' => ['error', 'error'],
    'warning' => ['warning', 'warning'],
    'alert' => ['alert', 'alert'],
    'info' => ['info', 'info'],
]);

test('toasts accumulate in call order', function () {
    $client = new OperationSPAClient();
    $client->error('First');
    $client->toast(new Toast(ToastType::SUCCESS, 'Second'));
    $client->warning('Third');

    expect($client->serializeToArray()['toasts'])->toBe([
        ['type' => 'error', 'message' => 'First'],
        ['type' => 'success', 'message' => 'Second'],
        ['type' => 'warning', 'message' => 'Third'],
    ]);
});

test('a redirect defaults to not reloading', function () {
    $client = new OperationSPAClient();
    $client->redirect('/orders');

    expect($client->serializeToArray())->toBe([
        'redirect' => ['url' => '/orders', 'reload' => false],
        'type' => 'operations-spa',
    ]);
});

test('a redirect can request a full reload', function () {
    $client = new OperationSPAClient();
    $client->redirect('/logout', true);

    expect($client->serializeToArray()['redirect'])->toBe(['url' => '/logout', 'reload' => true]);
});

test('the redirect slot holds a single directive, the last call wins', function () {
    $client = new OperationSPAClient();
    $client->redirect('/first', true);
    $client->redirect('/second');

    expect($client->serializeToArray()['redirect'])->toBe(['url' => '/second', 'reload' => false]);
});

test('an invalidation stringifies its namespace and appends the keys verbatim', function () {
    $client = new OperationSPAClient();
    $client->invalidate(InvalidationNamespace::USERS, 'get', ['id' => 1]);

    expect($client->serializeToArray())->toBe([
        'invalidations' => [
            ['users', 'get', ['id' => 1]],
        ],
        'type' => 'operations-spa',
    ]);
});

test('a pure enum namespace falls back to its case name', function () {
    $client = new OperationSPAClient();
    $client->invalidate(ResultEnum::SUCCESS);
    $client->invalidate('plain-string');

    expect($client->serializeToArray()['invalidations'])->toBe([
        ['SUCCESS'],
        ['plain-string'],
    ]);
});

test('every directive kind is emitted side by side', function () {
    $client = new OperationSPAClient();
    $client->success('Saved');
    $client->redirect('/orders/1');
    $client->invalidate(InvalidationNamespace::ORDERS, 'get');

    expect($client->serializeToArray())->toBe([
        'redirect' => ['url' => '/orders/1', 'reload' => false],
        'toasts' => [
            ['type' => 'success', 'message' => 'Saved'],
        ],
        'invalidations' => [
            ['orders', 'get'],
        ],
        'type' => 'operations-spa',
    ]);
});

test('the serialized directives are plain data, nothing relies on json_encode to unwrap objects', function () {
    $client = new OperationSPAClient();
    $client->warning('Careful');
    $client->redirect('/orders');
    $client->invalidate(InvalidationNamespace::ORDERS);

    $directives = $client->serializeToArray();
    $roundTripped = json_decode(json_encode($directives, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

    expect($roundTripped)->toBe($directives);
});
