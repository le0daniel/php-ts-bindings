<?php declare(strict_types=1);

use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidMiddlewareException;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidOutputException;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\PlainlyExposedKeyGenerator;
use Le0daniel\PhpTsBindings\Server\Operations\CachedOperationRegistry;
use Le0daniel\PhpTsBindings\CodeGen\Utils\ErrorTypescript;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedOperationRegistry;
use Le0daniel\PhpTsBindings\Server\Server;
use Tests\Feature\Mocks\GloballyThrowingMiddleware;
use Tests\Feature\Mocks\NotAMiddleware;

function executeOperation(string $name, mixed $input): RpcSuccess|RpcError {
    $registry = EagerlyLoadedOperationRegistry::eagerlyDiscover(__DIR__ . '/Operations', keyGenerator: new PlainlyExposedKeyGenerator);
    $cachedRegistry = eval(CachedOperationRegistry::toPhpCode($registry, idLength: 10));

    $server = new Server($registry);
    $cachedServer = new Server($cachedRegistry);

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

/**
 * The end of the road for a ValidationException: the value object rejects, the parse fails, and the
 * messages it chose come out the other side as the 422 the client reads. Nothing along the way -
 * InvalidInputException, ErrorPresenter, RpcError - is allowed to flatten them back to a key.
 */
test("a value object rejecting with ValidationException reaches the client as a 422 naming each message", function () {
    $error = executeOperation('test.acceptEmail', ['email' => '']);

    expect($error)->toBeInstanceOf(RpcError::class)
        ->and($error->type)->toBe(ErrorType::INVALID_INPUT)
        ->and($error->statusCode)->toBe(422)
        ->and($error->details)->toEqual([
            'fields' => [
                'email' => ['Email is required', 'Email must contain an @'],
            ],
        ]);

    expect(executeOperation('test.acceptEmail', ['email' => 'ada@example.test']))
        ->toBeInstanceOf(RpcSuccess::class);
});

test("A middleware that does not implement the contract yields an RpcError", function () {
    $server = new Server(
        EagerlyLoadedOperationRegistry::eagerlyDiscover(
            __DIR__ . '/Operations',
            keyGenerator: new PlainlyExposedKeyGenerator
        ),
        configuration: new ServerConfiguration()->withMiddlewares(NotAMiddleware::class),
    );

    $result = $server->command('test.run', ['name' => 'Leo'], null, new NullClient());

    // Named, not a TypeError from inside the adapter: the class-string is checked before
    // anything is constructed, so the message says which class and which contract.
    //
    // Two things fail here, and the chain keeps both: the class is rejected as a middleware, and
    // then reflecting the same class to work out what the operation exposes fails as well. The
    // second one is the most recent and is what made this a 500, so it is the cause.
    expect($result)->toBeInstanceOf(RpcError::class)
        ->and($result->type)->toBe(ErrorType::INTERNAL_ERROR)
        ->and($result->cause)->toBeInstanceOf(ReflectionException::class)
        ->and($result->previous)->toHaveCount(1)
        ->and($result->previous[0])->toBeInstanceOf(TypeError::class)
        ->and($result->previous[0]->getMessage())->toContain(NotAMiddleware::class);
});

test("Middleware emits typescript middleware", function () {
    $server = new Server(
        EagerlyLoadedOperationRegistry::eagerlyDiscover(
            __DIR__ . '/Operations',
            keyGenerator: new PlainlyExposedKeyGenerator
        ),
    );

    $operation = $server->registry->get(OperationType::COMMAND, 'test.run');
    $union = ErrorTypescript::forOperation($server->configuration, $operation->definition);

    expect($union)->toContain('{code: 400, type: "DOMAIN_ERROR", details: {type: "invalid_name"}}');
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

test('an output that does not match its declared type is an internal error, not a nulled branch', function () {
    // partialFailures would substitute null for the whole `user` branch and answer 200 with data
    // the operation never produced. Server turns it off, so the mismatch surfaces.
    $result = executeOperation('test.badOutput', ['ping' => true]);

    expect($result)->toBeInstanceOf(RpcError::class)
        ->and($result->type)->toBe(ErrorType::INTERNAL_ERROR)
        ->and($result->cause)->toBeInstanceOf(InvalidOutputException::class);
});

test('a globally configured middleware contributes its #[Throws] to the runtime and the codegen', function () {
    // Definition::$middleware only ever held what #[Middleware] put there, so a #[Throws] on a
    // middleware registered through ServerConfiguration was ignored by both the presenter and the
    // generated error union - the exception surfaced as a 500.
    $registry = EagerlyLoadedOperationRegistry::eagerlyDiscover(
        __DIR__ . '/Operations',
        keyGenerator: new PlainlyExposedKeyGenerator(),
    );
    $configuration = new ServerConfiguration()->withMiddlewares(GloballyThrowingMiddleware::class);
    $server = new Server($registry, configuration: $configuration);

    $error = $server->command('test.run', ['name' => 'global-boom'], null, new NullClient());

    expect($error)->toBeInstanceOf(RpcError::class)
        ->and($error->type)->toBe(ErrorType::DOMAIN_ERROR)
        ->and($error->details)->toEqual(['type' => 'global_middleware_failed']);

    $errorUnion = ErrorTypescript::forOperation(
        $configuration,
        $registry->get(OperationType::COMMAND, 'test.run')->definition,
    );

    expect($errorUnion)->toContain('"global_middleware_failed"');
});

test('an operation level declaration still wins over a global one for the same exception', function () {
    $registry = EagerlyLoadedOperationRegistry::eagerlyDiscover(
        __DIR__ . '/Operations',
        keyGenerator: new PlainlyExposedKeyGenerator(),
    );
    $configuration = new ServerConfiguration()->withMiddlewares(GloballyThrowingMiddleware::class);

    // test.run declares InvalidNameException itself; the global middleware must not displace it.
    $error = new Server($registry, configuration: $configuration)
        ->command('test.run', ['name' => 'invalid'], null, new NullClient());

    expect($error->details)->toEqual(['type' => 'invalid_name']);
});
