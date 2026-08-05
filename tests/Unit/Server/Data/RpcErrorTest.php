<?php declare(strict_types=1);

use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;

function errorInfo(): ResolveInfo
{
    return new ResolveInfo('users', 'create', OperationType::COMMAND, stdClass::class, 'create', []);
}

test('an error carries the category twice, as the status code and as the discriminant', function () {
    $error = new RpcError(ErrorType::NOT_FOUND, new RuntimeException('gone'), null, errorInfo());

    // toBe, not toEqual: === on arrays compares order too, and the wire shape is meant to be
    // byte comparable.
    expect($error->jsonSerialize())->toBe([
        'success' => false,
        'code' => 404,
        'type' => 'NOT_FOUND',
    ])->and($error->statusCode)->toBe(404);
});

test('a category that says everything on its own emits no details key', function (ErrorType $type) {
    $error = new RpcError($type, new RuntimeException('nope'), null, errorInfo());

    // Restating the category under `details.type` would be the same string on the wire twice, and
    // the generated branch declares no such property - narrowing on `type` must not offer one.
    expect($error->jsonSerialize())->not->toHaveKey('details');
})->with([
    'unauthenticated' => [ErrorType::AUTHENTICATION_ERROR],
    'unauthorized' => [ErrorType::AUTHORIZATION_ERROR],
    'not found' => [ErrorType::NOT_FOUND],
    'internal' => [ErrorType::INTERNAL_ERROR],
]);

test('the two categories the code alone cannot describe carry their details', function () {
    $invalidInput = new RpcError(
        ErrorType::INVALID_INPUT,
        new RuntimeException('bad'),
        ['fields' => ['email' => ['validation.not_empty_string']]],
        errorInfo(),
    );

    $domain = new RpcError(
        ErrorType::DOMAIN_ERROR,
        new RuntimeException('nope'),
        ['type' => 'invalid_name'],
        errorInfo(),
    );

    expect($invalidInput->jsonSerialize())->toBe([
        'success' => false,
        'code' => 422,
        'type' => 'INVALID_INPUT',
        'details' => ['fields' => ['email' => ['validation.not_empty_string']]],
    ])->and($domain->jsonSerialize())->toBe([
        'success' => false,
        'code' => 400,
        'type' => 'DOMAIN_ERROR',
        'details' => ['type' => 'invalid_name'],
    ]);
});

test('metadata is absent while empty and present once a middleware attached some', function () {
    $error = new RpcError(ErrorType::INTERNAL_ERROR, new RuntimeException('boom'), null, errorInfo());

    expect($error->jsonSerialize())->not->toHaveKey('__metadata')
        ->and($error->appendMetadata(['durationMs' => 12])->jsonSerialize())->toBe([
            'success' => false,
            'code' => 500,
            'type' => 'INTERNAL_ERROR',
            '__metadata' => ['durationMs' => 12],
        ]);
});

test('a failure never carries client directives', function () {
    // An error result holds no Client at all, and that is the point: a handler that toasts "Saved"
    // and then throws must not have that toast reach the browser. Whatever was queued before the
    // failure is dropped with the request.
    $error = new RpcError(ErrorType::DOMAIN_ERROR, new RuntimeException('nope'), ['type' => 'x'], errorInfo())
        ->withMetadata(['some' => 'metadata']);

    expect($error->jsonSerialize())->not->toHaveKey('__client')
        ->and(new ReflectionClass(RpcError::class)->hasProperty('client'))->toBeFalse();
});

test('the throwable chain is the cause alone until presenting one failure produced another', function () {
    $cause = new RuntimeException('what the application threw');
    $ordinary = new RpcError(ErrorType::INTERNAL_ERROR, $cause, null, errorInfo());

    $presentationFailure = new LogicException('stale middleware class name');
    $chained = new RpcError(ErrorType::INTERNAL_ERROR, $presentationFailure, null, errorInfo(), previous: [$cause]);

    expect($ordinary->throwableChain())->toBe([$cause])
        // Oldest first, with the failure that decided the category last.
        ->and($chained->throwableChain())->toBe([$cause, $presentationFailure]);
});

test('neither the chain nor the debug detail leaks into the envelope', function () {
    $error = new RpcError(
        ErrorType::INTERNAL_ERROR,
        new RuntimeException('database credentials rejected'),
        null,
        errorInfo(),
        previous: [new LogicException('and how we got there')],
    );

    expect($error->jsonSerialize())->toBe([
        'success' => false,
        'code' => 500,
        'type' => 'INTERNAL_ERROR',
    ]);
});

test('the envelope survives a json round trip as plain data', function () {
    $error = new RpcError(
        ErrorType::INVALID_INPUT,
        new RuntimeException('bad'),
        ['fields' => ['email' => ['validation.not_empty_string']]],
        errorInfo(),
    )->withMetadata(['trace' => ['one', 'two']]);

    $payload = $error->jsonSerialize();
    $roundTripped = json_decode(json_encode($error, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

    expect($roundTripped)->toBe($payload);
});
