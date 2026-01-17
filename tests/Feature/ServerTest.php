<?php declare(strict_types=1);

use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\PlainlyExposedKeyGenerator;
use Le0daniel\PhpTsBindings\Server\Operations\CachedOperationRegistry;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedRegistry;
use Le0daniel\PhpTsBindings\Server\Presenter\ClientAwareExceptionPresenter;
use Le0daniel\PhpTsBindings\Server\Server;

function executeOperation(string $name, mixed $input): RpcSuccess|RpcError {
    $registry = EagerlyLoadedRegistry::eagerlyDiscover(__DIR__ . '/Operations', keyGenerator: new PlainlyExposedKeyGenerator);
    $cachedRegistry = eval(CachedOperationRegistry::toPhpCode($registry));

    $server = new Server($registry, [new ClientAwareExceptionPresenter(),],);
    $cachedServer = new Server($cachedRegistry, [new ClientAwareExceptionPresenter(),],);

    $regularResponse = $server->command($name, $input, null, new NullClient());
    $cachedResponse = $cachedServer->command($name, $input, null, new NullClient());

    expect($regularResponse::class)->toEqual($cachedResponse::class);

    if ($regularResponse instanceof RpcSuccess) {
        $serializedRegularResponse = json_encode($regularResponse->data, JSON_THROW_ON_ERROR);
        $serializedCachedResponse = json_encode($cachedResponse->data, JSON_THROW_ON_ERROR);
        expect($serializedRegularResponse)->toEqual($serializedCachedResponse);
    } else {
        expect($regularResponse->type)->toEqual($cachedResponse->type);
    }


    return $regularResponse;
}

test("Exceptions are exposed through middleware", function () {
    $result = executeOperation( 'test.run', ['name' => 'Leo']);

    expect($result)->toBeInstanceOf(RpcSuccess::class)
        ->and($result->data)
        ->toEqual((object) ['message' => 'Hello Leo']);

    $error = executeOperation('test.run', ['name' => 'invalid']);

    expect($error)->toBeInstanceOf(RpcError::class)
        ->and($error->type)->toBe(ErrorType::DOMAIN_ERROR)
        ->and($error->details)->toEqual([
            'type' => 'invalid_name',
        ]);
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