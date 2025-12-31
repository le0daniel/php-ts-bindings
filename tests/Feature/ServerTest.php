<?php declare(strict_types=1);

use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\PlainlyExposedKeyGenerator;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedRegistry;
use Le0daniel\PhpTsBindings\Server\Presenter\ClientAwareExceptionPresenter;
use Le0daniel\PhpTsBindings\Server\Server;

test("Exceptions are cought from middleware", function () {
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
        ->and($result->data)->toEqual((object) ['message' => 'Hello Leo']);

    $error = $server->command('test.run', ['name' => 'invalid'], null, new NullClient());

    expect($error)->toBeInstanceOf(RpcError::class)
        ->and($error->type)->toBe(ErrorType::DOMAIN_ERROR)
        ->and($error->details)->toEqual([
            'type' => 'invalid_name',
        ]);
});