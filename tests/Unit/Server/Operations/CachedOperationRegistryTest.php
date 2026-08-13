<?php

declare(strict_types=1);

namespace Tests\Unit\Server\Operations;

use Le0daniel\PhpTsBindings\Executor\Exceptions\SchemaException;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\OperationNotFoundException;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\PlainlyExposedKeyGenerator;
use Le0daniel\PhpTsBindings\Server\Operations\CachedOperationRegistry;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedOperationRegistry;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Tests\Unit\Server\Operations\Mocks\RegistryFixtureOperations;

function compiledRegistryCode(): string
{
    $registry = EagerlyLoadedOperationRegistry::withClasses(
        [RegistryFixtureOperations::class],
        keyGenerator: new PlainlyExposedKeyGenerator(),
    );

    return CachedOperationRegistry::toPhpCode($registry, idLength: 10);
}

test('the compiled registry answers has() and builds an operation only on get(), memoized', function () {
    $cached = eval(compiledRegistryCode());

    expect($cached)->toBeInstanceOf(CachedOperationRegistry::class)
        ->and($cached->has(OperationType::QUERY, 'registry.greet'))->toBeTrue()
        ->and($cached->has(OperationType::COMMAND, 'registry.rename'))->toBeTrue()
        ->and($cached->has(OperationType::COMMAND, 'registry.greet'))->toBeFalse();

    $operation = $cached->get(OperationType::QUERY, 'registry.greet');

    expect($operation)->toBeInstanceOf(Operation::class)
        ->and($operation->key)->toBe('registry.greet')
        ->and($cached->get(OperationType::QUERY, 'registry.greet'))->toBe($operation);
});

test('all() materializes every operation keyed by its registry key', function () {
    $cached = eval(compiledRegistryCode());

    expect($cached->all())
        ->toHaveCount(2)
        ->toHaveKeys(['QUERY@registry.greet', 'COMMAND@registry.rename']);
});

test('the compiled code shares one factory across operations instead of allocating one closure per entry', function () {
    $code = compiledRegistryCode();
    $operationClass = PHPExport::absolute(Operation::class);

    // The legacy shape was `'KEY' => fn() => new Operation(...)`, one closure allocated per
    // operation on every require of the cache file. A match arm costs nothing until its key
    // is requested.
    expect($code)->not->toContain("fn() => new {$operationClass}(")
        ->and($code)->toContain("'QUERY@registry.greet' => new {$operationClass}(")
        ->and($code)->toContain("'QUERY@registry.greet' => true");
});

test('asking the compiled registry for an unknown key names the key', function () {
    $cached = eval(compiledRegistryCode());

    expect(fn () => $cached->get(OperationType::QUERY, 'registry.missing'))
        ->toThrow(OperationNotFoundException::class, 'QUERY@registry.missing');
});

test('a cache in the legacy one-closure-per-operation format is rejected with the reason', function () {
    expect(fn () => new CachedOperationRegistry(['QUERY@x' => static fn () => null]))
        ->toThrow(SchemaException::class, 'Regenerate the operations cache');
});
