<?php declare(strict_types=1);

use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidMiddlewareException;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\PlainlyExposedKeyGenerator;
use Le0daniel\PhpTsBindings\Server\Operations\CachedOperationRegistry;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedRegistry;
use Le0daniel\PhpTsBindings\Server\Presenter\ExposedExceptionPresenter;
use Le0daniel\PhpTsBindings\Server\Server;
use Tests\Feature\Mocks\NotAMiddleware;

function executeOperation(string $name, mixed $input): RpcSuccess|RpcError {
    $registry = EagerlyLoadedRegistry::eagerlyDiscover(__DIR__ . '/Operations', keyGenerator: new PlainlyExposedKeyGenerator);
    $cachedRegistry = eval(CachedOperationRegistry::toPhpCode($registry, idLength: 10));

    $server = new Server($registry, [new ExposedExceptionPresenter(),],);
    $cachedServer = new Server($cachedRegistry, [new ExposedExceptionPresenter(),],);

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

test("A middleware that does not implement the contract yields an RpcError", function () {
    $server = new Server(
        EagerlyLoadedRegistry::eagerlyDiscover(
            __DIR__ . '/Operations',
            keyGenerator: new PlainlyExposedKeyGenerator
        ),
        [
            new ExposedExceptionPresenter(),
        ],
        configuration: new ServerConfiguration()->withMiddlewares(NotAMiddleware::class),
    );

    $result = $server->command('test.run', ['name' => 'Leo'], null, new NullClient());

    expect($result)->toBeInstanceOf(RpcError::class)
        ->and($result->type)->toBe(ErrorType::INTERNAL_ERROR)
        ->and($result->cause)->toBeInstanceOf(InvalidMiddlewareException::class);
});

test("Middleware emits typescript middleware", function () {
    $server = new Server(
        EagerlyLoadedRegistry::eagerlyDiscover(
            __DIR__ . '/Operations',
            keyGenerator: new PlainlyExposedKeyGenerator
        ),
        [
            new ExposedExceptionPresenter(),
        ],
    );

    $operation = $server->registry->get(OperationType::COMMAND, 'test.run');
    $errorPresenter = new ExposedExceptionPresenter();
    $definition = $errorPresenter->toTypeScriptDefinition($operation->definition);
    expect($definition)->toEqual('{type: "invalid_name"}');
});
/**
 * The cached registry pools every operation's schemas together, so these cases only mean anything
 * on the production path: executeOperation() compares the eagerly discovered server against the
 * generated cache for each one.
 */
test('a constrained schema keeps its constraint when pooled with an unconstrained twin', function () {
    expect(executeOperation('pooling.constrainedEmail', ['email' => 'a@b.c']))->toBeInstanceOf(RpcSuccess::class)
        ->and(executeOperation('pooling.constrainedEmail', ['email' => '']))->toBeInstanceOf(RpcError::class)
        ->and(executeOperation('pooling.looseEmail', ['email' => '']))->toBeInstanceOf(RpcSuccess::class);
});

test('property key order is identical cached and uncached', function () {
    $result = executeOperation('pooling.declarationOrder', ['zebra' => 'z', 'alpha' => 'a', 'middle' => 1]);

    expect($result)->toBeInstanceOf(RpcSuccess::class)
        ->and(json_encode($result->data, JSON_THROW_ON_ERROR))
        ->toBe('{"alpha":"a","middle":1,"zebra":"z"}');
});

test('the same shape declared in two orders behaves identically', function () {
    $data = ['zebra' => 'z', 'alpha' => 'a', 'middle' => 1];

    expect(json_encode(executeOperation('pooling.declarationOrder', $data)->data, JSON_THROW_ON_ERROR))
        ->toBe(json_encode(executeOperation('pooling.reversedOrder', $data)->data, JSON_THROW_ON_ERROR));
});

test('int and float literal schemas do not merge when pooled', function () {
    expect(executeOperation('pooling.intLiteral', ['value' => 1]))->toBeInstanceOf(RpcSuccess::class)
        ->and(executeOperation('pooling.floatLiteral', ['value' => 1.0]))->toBeInstanceOf(RpcSuccess::class);
});
