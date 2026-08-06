<?php

declare(strict_types=1);

use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Client\OperationSPAClient;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;

function successResolveInfo(): ResolveInfo
{
    return new ResolveInfo('users', 'get', OperationType::QUERY, stdClass::class, 'get', []);
}

test('the success envelope is the two keys the generated Success<T> declares', function () {
    $result = new RpcSuccess(['id' => '123'], new NullClient(), successResolveInfo());

    // toBe, not toEqual: === on arrays compares order too, and the wire shape is meant to be
    // byte comparable.
    expect($result->jsonSerialize())->toBe([
        'success' => true,
        'data' => ['id' => '123'],
    ]);
});

test('null data keeps its key rather than vanishing from the envelope', function () {
    // Success<T> promises `data: T`, and an operation with a nullable output that returns null is
    // an ordinary success. Dropping the key would hand the client `undefined` for a declared null.
    $result = new RpcSuccess(null, new NullClient(), successResolveInfo());

    expect($result->jsonSerialize())->toBe([
        'success' => true,
        'data' => null,
    ]);
});

test('a client that is not serializable contributes no __client key', function () {
    $result = new RpcSuccess('ok', new NullClient(), successResolveInfo());

    expect($result->jsonSerialize())->not->toHaveKey('__client');
});

test('a serializable client with nothing queued contributes no __client key', function () {
    $result = new RpcSuccess('ok', new OperationSPAClient(), successResolveInfo());

    expect($result->jsonSerialize())->not->toHaveKey('__client');
});

test('the directives a serializable client collected ride along under __client', function () {
    $client = new OperationSPAClient();
    $client->success('Saved');
    $client->redirect('/users/123');

    $result = new RpcSuccess(['id' => '123'], $client, successResolveInfo());

    expect($result->jsonSerialize())->toBe([
        '__client' => [
            'redirect' => ['url' => '/users/123', 'reload' => false],
            'toasts' => [
                ['type' => 'success', 'message' => 'Saved'],
            ],
            'type' => 'operations-spa',
        ],
        'success' => true,
        'data' => ['id' => '123'],
    ]);
});

test('metadata is absent while empty and present once a middleware attached some', function () {
    $result = new RpcSuccess('ok', new NullClient(), successResolveInfo());

    expect($result->jsonSerialize())->not->toHaveKey('__metadata')
        ->and($result->appendMetadata(['durationMs' => 12])->jsonSerialize())->toBe([
            '__metadata' => ['durationMs' => 12],
            'success' => true,
            'data' => 'ok',
        ]);
});

test('withMetadata replaces the bag while appendMetadata merges into it', function () {
    $result = new RpcSuccess('ok', new NullClient(), successResolveInfo())
        ->withMetadata(['first' => 1]);

    expect($result->appendMetadata(['second' => 2])->metadata)->toBe(['first' => 1, 'second' => 2])
        ->and($result->withMetadata(['second' => 2])->metadata)->toBe(['second' => 2]);
});

test('the envelope survives a json round trip as plain data', function () {
    $client = new OperationSPAClient();
    $client->warning('Careful');

    $result = new RpcSuccess(['id' => 1], $client, successResolveInfo())
        ->withMetadata(['trace' => ['one', 'two']]);

    $payload = $result->jsonSerialize();
    $roundTripped = json_decode(json_encode($result, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

    expect($roundTripped)->toBe($payload);
});

test('the status code is 200, so a transport never has to ask which outcome this is', function () {
    expect(new RpcSuccess(null, new NullClient(), successResolveInfo())->statusCode)->toBe(200);
});
