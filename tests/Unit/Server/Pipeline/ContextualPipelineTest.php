<?php declare(strict_types=1);

namespace Tests\Unit\Server\Pipeline;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;
use Le0daniel\PhpTsBindings\Server\Pipeline\ContextualPipeline;
use RuntimeException;
use stdClass;
use Throwable;

function pipelineResolveInfo(): ResolveInfo
{
    return new ResolveInfo('test', 'operation', OperationType::QUERY, stdClass::class, 'run', []);
}

/**
 * Appends one entry to the result's `trace` metadata, so the order in which rings enter and leave
 * is observable through the public RpcResult API instead of through a side channel.
 */
function trace(RpcSuccess|RpcError $result, string $entry): RpcSuccess|RpcError
{
    $existing = $result->metadata['trace'] ?? [];
    assert(is_array($existing));

    return $result->appendMetadata(['trace' => [...$existing, $entry]]);
}

/**
 * @param list<MiddlewareContract<string>> $middlewares
 * @param Closure(mixed): (RpcSuccess|RpcError) $destination
 * @param (Closure(Throwable): RpcError)|null $onError
 */
function runPipeline(array $middlewares, Closure $destination, ?Closure $onError = null): RpcSuccess|RpcError
{
    return new ContextualPipeline(
        middlewares: $middlewares,
        onError: $onError ?? fn(Throwable $throwable): RpcError => new RpcError(
            ErrorType::INTERNAL_ERROR,
            $throwable,
            ['type' => 'PRESENTED'],
            pipelineResolveInfo(),
        ),
        destination: $destination,
    )->execute('input', 'context', pipelineResolveInfo(), new NullClient());
}

/**
 * @param Closure(mixed, Closure(mixed): (RpcSuccess|RpcError), string, ResolveInfo, Client): (RpcSuccess|RpcError) $handle
 * @return MiddlewareContract<string>
 */
function middleware(Closure $handle): MiddlewareContract
{
    return new class($handle) implements MiddlewareContract {
        /**
         * @param Closure(mixed, Closure(mixed): (RpcSuccess|RpcError), string, ResolveInfo, Client): (RpcSuccess|RpcError) $handle
         */
        public function __construct(private readonly Closure $handle)
        {
        }

        public function handle(mixed $input, Closure $next, mixed $context, ResolveInfo $info, Client $client): RpcSuccess|RpcError
        {
            return ($this->handle)($input, $next, $context, $info, $client);
        }
    };
}

function succeed(mixed $data = 'ok'): RpcSuccess
{
    return new RpcSuccess($data, new NullClient(), pipelineResolveInfo());
}

test('the destination runs when there is no middleware', function () {
    $result = runPipeline([], fn(mixed $input): RpcSuccess => succeed($input));

    expect($result)->toBeInstanceOf(RpcSuccess::class)
        ->and($result->data)->toBe('input');
});

test('middlewares wrap the destination as an onion', function () {
    $result = runPipeline(
        [
            middleware(fn(mixed $input, Closure $next): RpcSuccess|RpcError => trace($next($input), 'exit first')),
            middleware(fn(mixed $input, Closure $next): RpcSuccess|RpcError => trace($next($input), 'exit second')),
        ],
        fn(mixed $input): RpcSuccess|RpcError => trace(succeed($input), 'destination'),
    );

    expect($result->metadata['trace'])->toBe(['destination', 'exit second', 'exit first']);
});

test('every middleware receives the context, the resolve info and the client', function () {
    $seen = null;

    $result = runPipeline(
        [
            middleware(function (mixed $input, Closure $next, mixed $context, ResolveInfo $info, Client $client) use (&$seen): RpcSuccess|RpcError {
                $seen = [$input, $context, $info->fullyQualifiedName, $client::class];
                return $next($input);
            }),
        ],
        fn(mixed $input): RpcSuccess => succeed($input),
    );

    expect($result)->toBeInstanceOf(RpcSuccess::class)
        ->and($seen)->toBe(['input', 'context', 'test.operation', NullClient::class]);
});

test('a middleware may short circuit without calling next', function () {
    $destinationRan = false;

    $result = runPipeline(
        [middleware(fn(): RpcSuccess => succeed('short circuited'))],
        function () use (&$destinationRan): RpcSuccess {
            $destinationRan = true;
            return succeed();
        },
    );

    expect($result->data)->toBe('short circuited')
        ->and($destinationRan)->toBeFalse();
});

test('a throwing middleware becomes an RpcError handed back to the enclosing middleware', function () {
    $result = runPipeline(
        [
            middleware(fn(mixed $input, Closure $next): RpcSuccess|RpcError => trace($next($input), 'exit outer')),
            middleware(function (): RpcSuccess|RpcError {
                throw new RuntimeException('inner exploded');
            }),
        ],
        fn(): RpcSuccess => succeed(),
    );

    // The outer ring keeps running: it saw an RpcError as the return value of $next(), not an exception.
    expect($result)->toBeInstanceOf(RpcError::class)
        ->and($result->metadata['trace'])->toBe(['exit outer'])
        ->and($result->cause->getMessage())->toBe('inner exploded')
        ->and($result->details)->toBe(['type' => 'PRESENTED']);
});

test('a throwing destination becomes an RpcError handed back to the innermost middleware', function () {
    $result = runPipeline(
        [middleware(fn(mixed $input, Closure $next): RpcSuccess|RpcError => trace($next($input), 'exit outer'))],
        function (): RpcSuccess {
            throw new RuntimeException('destination exploded');
        },
    );

    expect($result)->toBeInstanceOf(RpcError::class)
        ->and($result->metadata['trace'])->toBe(['exit outer'])
        ->and($result->cause->getMessage())->toBe('destination exploded');
});

test('an RpcError returned by a middleware travels outward untouched', function () {
    $presented = 0;

    $result = runPipeline(
        [
            middleware(fn(mixed $input, Closure $next): RpcSuccess|RpcError => trace($next($input), 'exit outer')),
            middleware(fn(): RpcError => new RpcError(
                ErrorType::AUTHORIZATION_ERROR,
                new RuntimeException('denied'),
                ['type' => 'FORBIDDEN'],
                pipelineResolveInfo(),
            )),
        ],
        fn(): RpcSuccess => succeed(),
        function (Throwable $throwable) use (&$presented): RpcError {
            $presented++;
            return new RpcError(ErrorType::INTERNAL_ERROR, $throwable, ['type' => 'PRESENTED'], pipelineResolveInfo());
        },
    );

    expect($result)->toBeInstanceOf(RpcError::class)
        ->and($result->type)->toBe(ErrorType::AUTHORIZATION_ERROR)
        ->and($result->details)->toBe(['type' => 'FORBIDDEN'])
        ->and($result->metadata['trace'])->toBe(['exit outer'])
        ->and($presented)->toBe(0);
});

test('the pipeline still returns an RpcError when the error handler itself fails', function () {
    $result = runPipeline(
        [
            middleware(function (): RpcSuccess|RpcError {
                throw new RuntimeException('inner exploded');
            }),
        ],
        fn(): RpcSuccess => succeed(),
        function (): RpcError {
            throw new RuntimeException('the presenter is broken too');
        },
    );

    expect($result)->toBeInstanceOf(RpcError::class)
        ->and($result->type)->toBe(ErrorType::INTERNAL_ERROR)
        ->and($result->details)->toBe(['type' => 'INTERNAL_SERVER_ERROR'])
        ->and($result->cause->getMessage())->toBe('the presenter is broken too')
        ->and($result->resolveInfo?->fullyQualifiedName)->toBe('test.operation');
});
