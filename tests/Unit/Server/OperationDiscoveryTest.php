<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Middleware;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Executor\Exceptions\SchemaException;
use Le0daniel\PhpTsBindings\Server\Operations\OperationDiscovery;
use ReflectionClass;
use Tests\Feature\Mocks\GloballyThrowingMiddleware;
use Tests\Feature\Operations\NameCheckingMiddleware;

function discover(object|string $class): OperationDiscovery
{
    $discovery = new OperationDiscovery();
    $discovery->discover(new ReflectionClass($class));

    return $discovery;
}

final class ClientInContextSlot
{
    /**
     * @param  array{a: string}  $input
     * @return array{a: string}
     */
    #[Command('bad')]
    public function run(array $input, Client $client): array
    {
        return $input;
    }
}

final class TooManyParameters
{
    /**
     * @param  array{a: string}  $input
     * @return array{a: string}
     */
    #[Command('bad')]
    public function run(array $input, mixed $context, Client $client, string $extra): array
    {
        return $input;
    }
}

final class WrongClientType
{
    /**
     * @param  array{a: string}  $input
     * @return array{a: string}
     */
    #[Command('bad')]
    public function run(array $input, mixed $context, string $client): array
    {
        return $input;
    }
}

final class ValidPrefixes
{
    /**
     * @param  array{a: string}  $input
     * @return array{a: string}
     */
    #[Command('ok', 'inputOnly')]
    public function inputOnly(array $input): array
    {
        return $input;
    }

    /**
     * @param  array{a: string}  $input
     * @return array{a: string}
     */
    #[Command('ok', 'withContext')]
    public function withContext(array $input, mixed $context): array
    {
        return $input;
    }

    /**
     * @param  array{a: string}  $input
     * @return array{a: string}
     */
    #[Command('ok', 'withClient')]
    public function withClient(array $input, mixed $context, Client $client): array
    {
        return $input;
    }
}

#[Middleware(GloballyThrowingMiddleware::class)]
final class StackedMiddleware
{
    /**
     * @param  array{a: string}  $input
     * @return array{a: string}
     */
    #[Command('stacked')]
    #[Middleware(NameCheckingMiddleware::class)]
    public function run(array $input): array
    {
        return $input;
    }
}

test('a handler may declare any prefix of (input, context, client)', function () {
    expect(discover(ValidPrefixes::class)->operations)->toHaveCount(3);
});

test('a Client in the context slot is rejected with the reason', function () {
    // It would silently receive the context and die with a TypeError naming neither.
    expect(fn () => discover(ClientInContextSlot::class))
        ->toThrow(SchemaException::class, 'the second argument is the context');
});

test('more parameters than the handler contract has are rejected', function () {
    expect(fn () => discover(TooManyParameters::class))
        ->toThrow(SchemaException::class, 'may declare a prefix of those');
});

test('a third parameter that cannot accept a Client is rejected', function () {
    expect(fn () => discover(WrongClientType::class))
        ->toThrow(SchemaException::class, 'which is the client');
});

test('repeated #[Middleware] attributes stack, class level before method level', function () {
    // The order is what ContextualPipeline nests them in, so class level wraps method level.
    $definition = discover(StackedMiddleware::class)->operations |> array_values(...);

    expect($definition[0]->middleware)->toBe([
        GloballyThrowingMiddleware::class,
        NameCheckingMiddleware::class,
    ]);
});
