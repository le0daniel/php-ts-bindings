<?php declare(strict_types=1);

use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\PlainlyExposedKeyGenerator;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedRegistry;
use Le0daniel\PhpTsBindings\Server\Presenter\ClientAwareExceptionPresenter;
use Le0daniel\PhpTsBindings\Server\Server;

test("Exceptions are exposed through middleware", function () {
    $server = new Server(
        EagerlyLoadedRegistry::eagerlyDiscover(
            __DIR__ . '/Operations',
            keyGenerator: new PlainlyExposedKeyGenerator
        ),
        [
            new ClientAwareExceptionPresenter(),
        ],
    );

    $result = $server->command('test.run', ['name' => 'Leo'], null, new NullClient());

    expect($result)->toBeInstanceOf(RpcSuccess::class)
        ->and($result->data)
        ->toEqual((object) ['message' => 'Hello Leo']);

    $error = $server->command('test.run', ['name' => 'invalid'], null, new NullClient());

    expect($error)->toBeInstanceOf(RpcError::class)
        ->and($error->type)->toBe(ErrorType::DOMAIN_ERROR)
        ->and($error->details)->toEqual([
            'type' => 'invalid_name',
        ]);
});

test("Test DateTime string", function () {
    $server = new Server(
        EagerlyLoadedRegistry::eagerlyDiscover(
            __DIR__ . '/Operations',
            keyGenerator: new PlainlyExposedKeyGenerator
        ),
        [
            new ClientAwareExceptionPresenter(),
        ],
    );

    $result = $server->command('test.someDateStuff', ['dueDate' => '2023-01-19'], null, new NullClient());

    expect($result)->toBeInstanceOf(RpcSuccess::class)
        ->and($result->data)
        ->toEqual((object) ['message' => 'Date Is 19.01.2023', 'date' => '19.01.2023']);

    $invalidInputResult = $server->command('test.someDateStuff', ['dueDate' => 'invalid'], null, new NullClient());
    expect($invalidInputResult)->toBeInstanceOf(RpcError::class)
        ->and($invalidInputResult->type)->toBe(ErrorType::INTERNAL_ERROR)
        ->and($invalidInputResult->cause->getMessage())->toBe("Input validation failed");
});

test("Middleware emits typescript middleware", function () {
    $server = new Server(
        EagerlyLoadedRegistry::eagerlyDiscover(
            __DIR__ . '/Operations',
            keyGenerator: new PlainlyExposedKeyGenerator
        ),
        [
            new ClientAwareExceptionPresenter(),
        ],
    );

    $operation = $server->registry->get(OperationType::COMMAND, 'test.run');
    $errorPresenter = new ClientAwareExceptionPresenter();
    $definition = $errorPresenter->toTypeScriptDefinition($operation->definition);
    expect($definition)->toEqual('{type: "invalid_name"}');
});