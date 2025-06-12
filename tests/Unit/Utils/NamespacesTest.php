<?php

namespace Tests\Unit\Utils;

use Le0daniel\PhpTsBindings\Utils\Namespaces;

test('to fully qualified class name', function () {

    expect(Namespaces::toFullyQualifiedClassName('Bar', 'Foo', []))->toBe("Foo\\Bar");
    expect(Namespaces::toFullyQualifiedClassName('\\Bar', 'Foo', []))->toBe("Bar");
    expect(Namespaces::toFullyQualifiedClassName('MyClass', 'Foo', ['MyClass' => 'App\\Utils']))->toBe("App\\Utils\\MyClass");
});

test('build namespace alias map', function () {
    expect(Namespaces::buildNamespaceAliasMap([]))->toBe([]);

    $namespaces = [
        'App\\Models\\User',
        'App\\Services\\PaymentService' => 'Payments',
        '\\App\\Utils\\Strings',
        '\\App\\Utils\\Arrays' => 'Arr',
    ];

    $expectedMap = [
        'User' => 'App\\Models\\User',
        'Payments' => 'App\\Services\\PaymentService',
        'Strings' => 'App\\Utils\\Strings',
        'Arr' => 'App\\Utils\\Arrays',
    ];

    expect(Namespaces::buildNamespaceAliasMap($namespaces))->toEqual($expectedMap);
});
