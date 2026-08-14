<?php

declare(strict_types=1);

use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Tests\Mocks\Errors\RecordMissingException;
use Tests\Unit\Server\Errors\Mocks\TooManyAttemptsException;
use Tests\Unit\Server\Errors\Mocks\UnauthenticatedException;
use Tests\Unit\Server\Errors\Mocks\UnauthorizedException;

/**
 * Every wither rebuilds the readonly instance. This pins that none of them silently drops a
 * field - the drift a new field is most likely to introduce.
 */
function fullyConfigured(): ServerConfiguration
{
    return new ServerConfiguration(
        coerceQueryInput: true,
        middleware: [],
        notFoundExceptions: [RecordMissingException::class],
        unauthenticatedExceptions: [UnauthenticatedException::class],
        unauthorizedExceptions: [UnauthorizedException::class],
        rateLimitedExceptions: [TooManyAttemptsException::class],
        resolveRetryIn: static fn (Throwable $throwable): ?int => 30,
    );
}

test('adding middlewares preserves every other field', function () {
    $before = fullyConfigured();
    $after = $before->withMiddlewares();

    expect($after->coerceQueryInput)->toBe($before->coerceQueryInput)
        ->and($after->notFoundExceptions)->toBe($before->notFoundExceptions)
        ->and($after->unauthenticatedExceptions)->toBe($before->unauthenticatedExceptions)
        ->and($after->unauthorizedExceptions)->toBe($before->unauthorizedExceptions)
        ->and($after->rateLimitedExceptions)->toBe($before->rateLimitedExceptions)
        ->and($after->resolveRetryIn)->toBe($before->resolveRetryIn);
});

test('adding exceptions appends per category and preserves every other field', function () {
    $before = fullyConfigured();
    $after = $before->withExceptions(
        notFound: [TooManyAttemptsException::class],
        rateLimited: [RecordMissingException::class],
    );

    expect($after->notFoundExceptions)->toBe([RecordMissingException::class, TooManyAttemptsException::class])
        ->and($after->rateLimitedExceptions)->toBe([TooManyAttemptsException::class, RecordMissingException::class])
        ->and($after->unauthenticatedExceptions)->toBe($before->unauthenticatedExceptions)
        ->and($after->unauthorizedExceptions)->toBe($before->unauthorizedExceptions)
        ->and($after->coerceQueryInput)->toBe($before->coerceQueryInput)
        ->and($after->middleware)->toBe($before->middleware)
        ->and($after->resolveRetryIn)->toBe($before->resolveRetryIn);
});

test('setting the retryIn resolver preserves every other field', function () {
    $before = fullyConfigured();
    $resolver = static fn (Throwable $throwable): ?int => null;
    $after = $before->withRetryInResolver($resolver);

    expect($after->resolveRetryIn)->toBe($resolver)
        ->and($after->coerceQueryInput)->toBe($before->coerceQueryInput)
        ->and($after->middleware)->toBe($before->middleware)
        ->and($after->notFoundExceptions)->toBe($before->notFoundExceptions)
        ->and($after->unauthenticatedExceptions)->toBe($before->unauthenticatedExceptions)
        ->and($after->unauthorizedExceptions)->toBe($before->unauthorizedExceptions)
        ->and($after->rateLimitedExceptions)->toBe($before->rateLimitedExceptions);
});
