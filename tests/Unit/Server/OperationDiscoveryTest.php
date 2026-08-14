<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Middleware;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Executor\Exceptions\SchemaException;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidMiddlewareException;
use Le0daniel\PhpTsBindings\Server\Operations\OperationDiscovery;
use ReflectionClass;
use Tests\Feature\Mocks\GloballyThrowingMiddleware;
use Tests\Feature\Operations\NameCheckingMiddleware;
use Tests\Feature\Operations\PrefixNameMiddleware;

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

final class ConfiguredMiddlewareOperation
{
    /**
     * @param  array{name: string}  $input
     * @return array{name: string}
     */
    #[Command('configured')]
    #[Middleware(PrefixNameMiddleware::class, config: ['prefix' => 'Dr. '])]
    public function run(array $input): array
    {
        return $input;
    }
}

final class NotConfigurableOperation
{
    /**
     * @param  array{name: string}  $input
     * @return array{name: string}
     */
    #[Command('configured')]
    #[Middleware(NameCheckingMiddleware::class, config: ['prefix' => 'Dr. '])]
    public function run(array $input): array
    {
        return $input;
    }
}

final class ListConfigOperation
{
    /**
     * @param  array{name: string}  $input
     * @return array{name: string}
     */
    #[Command('configured')]
    #[Middleware(PrefixNameMiddleware::class, config: ['zero-indexed'])]
    public function run(array $input): array
    {
        return $input;
    }
}

final class NestedConfigOperation
{
    /**
     * @param  array{name: string}  $input
     * @return array{name: string}
     */
    #[Command('configured')]
    #[Middleware(PrefixNameMiddleware::class, config: ['options' => ['nested' => true]])]
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

    expect($definition[0]->middlewareClassNames())->toBe([
        GloballyThrowingMiddleware::class,
        NameCheckingMiddleware::class,
    ]);
});

test('middleware config is captured on the definition', function () {
    $definitions = discover(ConfiguredMiddlewareOperation::class)->operations |> array_values(...);

    expect($definitions[0]->middleware)->toHaveCount(1)
        ->and($definitions[0]->middleware[0]->middleware)->toBe(PrefixNameMiddleware::class)
        ->and($definitions[0]->middleware[0]->config)->toBe(['prefix' => 'Dr. '])
        ->and($definitions[0]->middlewareClassNames())->toBe([PrefixNameMiddleware::class]);
});

test('config on a middleware that does not implement ConfigurableMiddleware is rejected', function () {
    expect(fn () => discover(NotConfigurableOperation::class))
        ->toThrow(InvalidMiddlewareException::class, 'ConfigurableMiddleware');
});

test('config with non-string keys is rejected at discovery', function () {
    expect(fn () => discover(ListConfigOperation::class))
        ->toThrow(InvalidMiddlewareException::class, 'array<string, scalar>');
});

test('config with non-scalar values is rejected at discovery', function () {
    expect(fn () => discover(NestedConfigOperation::class))
        ->toThrow(InvalidMiddlewareException::class, 'array<string, scalar>');
});
