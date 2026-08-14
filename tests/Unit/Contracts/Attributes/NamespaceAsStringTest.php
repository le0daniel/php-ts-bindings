<?php

declare(strict_types=1);

namespace Tests\Unit\Contracts\Attributes;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Tests\Mocks\ResultEnum;
use Tests\Mocks\ValueObjects\StatusEnum;

test('namespaceAsString returns null only when no namespace was given', function () {
    expect(new Query()->namespaceAsString())->toBeNull()
        ->and(new Command()->namespaceAsString())->toBeNull();
});

test('namespaceAsString resolves a string namespace verbatim', function () {
    expect(new Query(namespace: 'users')->namespaceAsString())->toBe('users')
        ->and(new Command(namespace: 'users')->namespaceAsString())->toBe('users');
});

test('namespaceAsString resolves a backed enum to its value', function () {
    expect(new Query(namespace: StatusEnum::ACTIVE)->namespaceAsString())->toBe('active')
        ->and(new Command(namespace: StatusEnum::ACTIVE)->namespaceAsString())->toBe('active');
});

test('namespaceAsString resolves a pure enum to its case name', function () {
    expect(new Query(namespace: ResultEnum::SUCCESS)->namespaceAsString())->toBe('SUCCESS');
});

/**
 * "0" is falsy in PHP but is a perfectly legal namespace. A truthiness check silently dropped it,
 * so OperationDiscovery fell back to the default namespace and the operation was registered under
 * a key the author never wrote.
 */
test('namespaceAsString keeps a namespace that happens to be falsy', function (string $namespace) {
    expect(new Query(namespace: $namespace)->namespaceAsString())->toBe($namespace)
        ->and(new Command(namespace: $namespace)->namespaceAsString())->toBe($namespace);
})->with(['zero string' => ['0'], 'empty string' => ['']]);
