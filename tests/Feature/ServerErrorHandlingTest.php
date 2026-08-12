<?php

declare(strict_types=1);

use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\PlainlyExposedKeyGenerator;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedOperationRegistry;
use Le0daniel\PhpTsBindings\Server\Server;
use Tests\Feature\ErrorHandling\ConflictException;
use Tests\Feature\ErrorHandling\ForbiddenException;
use Tests\Feature\ErrorHandling\GoneException;
use Tests\Feature\ErrorHandling\SessionExpiredException;
use Tests\Feature\ErrorHandling\SharedException;
use Tests\Feature\ErrorHandling\TooManyRequestsException;

function errorHandlingServer(?ServerConfiguration $configuration = null): Server
{
    return new Server(
        EagerlyLoadedOperationRegistry::eagerlyDiscover(
            __DIR__.'/ErrorHandling',
            keyGenerator: new PlainlyExposedKeyGenerator(),
        ),
        configuration: $configuration ?? new ServerConfiguration(),
    );
}

function executeErrorOperation(string $name, string $value = 'go', ?ServerConfiguration $configuration = null): RpcSuccess|RpcError
{
    return errorHandlingServer($configuration)->command($name, ['value' => $value], null, new NullClient());
}

test('a domain error declared on the handler and thrown by it carries its name', function () {
    $error = executeErrorOperation('errors.throwsDeclaredDomainError');

    expect($error)->toBeInstanceOf(RpcError::class)
        ->and($error->type)->toBe(ErrorType::DOMAIN_ERROR)
        ->and($error->statusCode)->toBe(400)
        ->and($error->details)->toEqual(['name' => 'teapot']);
});

test('a declaration on the handler does not cover a middleware throwing the same exception', function () {
    $error = executeErrorOperation('errors.declaresButMiddlewareThrows');

    expect($error)->toBeInstanceOf(RpcError::class)
        ->and($error->type)->toBe(ErrorType::INTERNAL_ERROR)
        ->and($error->details)->toBeNull()
        ->and($error->cause)->toBeInstanceOf(SharedException::class);
});

test('a declaration on a middleware does not cover the handler throwing the same exception', function () {
    $error = executeErrorOperation('errors.throwsWhatOnlyMiddlewareDeclares');

    expect($error)->toBeInstanceOf(RpcError::class)
        ->and($error->type)->toBe(ErrorType::INTERNAL_ERROR)
        ->and($error->details)->toBeNull()
        ->and($error->cause)->toBeInstanceOf(SharedException::class);
});

test('a middleware naming its own throw yields that domain error', function () {
    $error = executeErrorOperation('errors.middlewareOwnDomainError');

    expect($error)->toBeInstanceOf(RpcError::class)
        ->and($error->type)->toBe(ErrorType::DOMAIN_ERROR)
        ->and($error->details)->toEqual(['name' => 'middleware_own_error']);
});

test('a #[Throws] with an explicit category maps the throw for its own scope', function () {
    $error = executeErrorOperation('errors.throwsMappedNotFound');

    expect($error)->toBeInstanceOf(RpcError::class)
        ->and($error->type)->toBe(ErrorType::NOT_FOUND)
        ->and($error->statusCode)->toBe(404)
        ->and($error->details)->toBeNull();
});

test('the configured lists classify what no scope declared', function (string $value, ErrorType $expected) {
    $configuration = new ServerConfiguration()->withExceptions(
        notFound: [GoneException::class],
        unauthenticated: [SessionExpiredException::class],
        unauthorized: [ForbiddenException::class],
    );

    $error = executeErrorOperation('errors.throwsUnclassified', $value, $configuration);

    expect($error)->toBeInstanceOf(RpcError::class)
        ->and($error->type)->toBe($expected)
        ->and($error->details)->toBeNull();
})->with([
    'unauthenticated' => ['unauthenticated', ErrorType::AUTHENTICATION_ERROR],
    'unauthenticated subclass' => ['unauthenticated-subclass', ErrorType::AUTHENTICATION_ERROR],
    'unauthorized' => ['unauthorized', ErrorType::AUTHORIZATION_ERROR],
    'not found' => ['not-found', ErrorType::NOT_FOUND],
    'unrecognised stays internal' => ['anything-else', ErrorType::INTERNAL_ERROR],
]);

test('the throwing scope declaration wins over a configured category for the same exception', function () {
    // The deleted ErrorPresenter resolved the configured lists first, so a listed exception stayed
    // in its category even when named. Deliberately inverted: the scope that threw knows best what
    // its own exception means, and the lists are the fallback.
    $configuration = new ServerConfiguration()->withExceptions(unauthenticated: [ConflictException::class]);

    $error = executeErrorOperation('errors.throwsDeclaredAndConfigured', configuration: $configuration);

    expect($error)->toBeInstanceOf(RpcError::class)
        ->and($error->type)->toBe(ErrorType::DOMAIN_ERROR)
        ->and($error->details)->toEqual(['name' => 'conflict']);
});

test('a listed rate limited exception carries the resolved retryIn', function () {
    $configuration = new ServerConfiguration()
        ->withExceptions(rateLimited: [TooManyRequestsException::class])
        ->withRetryInResolver(fn (Throwable $throwable): ?int => 30);

    $error = executeErrorOperation('errors.throwsUnclassified', 'rate-limited', $configuration);

    expect($error)->toBeInstanceOf(RpcError::class)
        ->and($error->type)->toBe(ErrorType::RATE_LIMITED)
        ->and($error->statusCode)->toBe(429)
        ->and($error->details)->toBe(['retryIn' => 30]);
});

test('a rate limited error without a resolver still carries the details with a null retryIn', function () {
    // The branch always declares {retryIn: number | null} - configuring a resolver must change
    // the value, never the shape.
    $configuration = new ServerConfiguration()->withExceptions(rateLimited: [TooManyRequestsException::class]);

    $error = executeErrorOperation('errors.throwsUnclassified', 'rate-limited', $configuration);

    expect($error)->toBeInstanceOf(RpcError::class)
        ->and($error->type)->toBe(ErrorType::RATE_LIMITED)
        ->and($error->details)->toBe(['retryIn' => null]);
});

test('a #[Throws] mapping to rate limited gets the resolved retryIn like the configured list does', function () {
    $configuration = new ServerConfiguration()
        ->withRetryInResolver(fn (Throwable $throwable): ?int => $throwable instanceof TooManyRequestsException ? 12 : null);

    $error = executeErrorOperation('errors.throwsMappedRateLimited', configuration: $configuration);

    expect($error)->toBeInstanceOf(RpcError::class)
        ->and($error->type)->toBe(ErrorType::RATE_LIMITED)
        ->and($error->statusCode)->toBe(429)
        ->and($error->details)->toBe(['retryIn' => 12]);
});

test('a throwing retryIn resolver surfaces as an internal error, not a broken rate limit', function () {
    // Presentation has one safety net: whatever fails while shaping the error becomes a 500.
    // A buggy resolver is a server bug and must not ship a half-formed 429.
    $configuration = new ServerConfiguration()
        ->withExceptions(rateLimited: [TooManyRequestsException::class])
        ->withRetryInResolver(fn (Throwable $throwable): ?int => throw new LogicException('resolver bug'));

    $error = executeErrorOperation('errors.throwsUnclassified', 'rate-limited', $configuration);

    expect($error)->toBeInstanceOf(RpcError::class)
        ->and($error->type)->toBe(ErrorType::INTERNAL_ERROR)
        ->and($error->details)->toBeNull();
});

test('an unknown operation is not found with no resolve info', function () {
    $error = errorHandlingServer()->command('errors.doesNotExist', ['value' => 'x'], null, new NullClient());

    expect($error)->toBeInstanceOf(RpcError::class)
        ->and($error->type)->toBe(ErrorType::NOT_FOUND)
        ->and($error->details)->toBeNull()
        ->and($error->resolveInfo)->toBeNull();
});
