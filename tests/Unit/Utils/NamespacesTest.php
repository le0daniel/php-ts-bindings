<?php

namespace Tests\Unit\Utils;

use Le0daniel\PhpTsBindings\Utils\Namespaces;

/**
 * The maps under test are built from a file's `use` statements, so every case here feeds
 * toFullyQualifiedClassName() a map that buildNamespaceAliasMap() can actually produce. A
 * hand-written map that cannot occur is how the doubled-segment bug stayed hidden.
 */
test('a leading backslash means the name is already fully qualified', function () {
    expect(Namespaces::toFullyQualifiedClassName('\\Bar', 'Foo', []))->toBe('Bar');
});

test('an unimported name is resolved against the current namespace', function () {
    expect(Namespaces::toFullyQualifiedClassName('Bar', 'Foo', []))->toBe('Foo\\Bar');
});

test('an imported class resolves to what it was imported as', function () {
    $map = Namespaces::buildNamespaceAliasMap(['App\\Utils\\MyClass']);

    expect(Namespaces::toFullyQualifiedClassName('MyClass', 'Foo', $map))->toBe('App\\Utils\\MyClass');
});

test('an aliased import resolves to the aliased class', function () {
    $map = Namespaces::buildNamespaceAliasMap(['App\\Contracts\\User' => 'UserContract']);

    expect(Namespaces::toFullyQualifiedClassName('UserContract', 'Foo', $map))->toBe('App\\Contracts\\User');
});

test('a sub path of an imported namespace appends only the remaining segments', function () {
    // use App\Models; then Models\User - the alias contributes App\Models, and only what follows
    // it is appended. Concatenating the whole short name produced App\Models\Models\User.
    $map = Namespaces::buildNamespaceAliasMap(['App\\Models']);

    expect(Namespaces::toFullyQualifiedClassName('Models\\User', 'App\\Http', $map))->toBe('App\\Models\\User')
        ->and(Namespaces::toFullyQualifiedClassName('Models\\Nested\\User', 'App\\Http', $map))
        ->toBe('App\\Models\\Nested\\User');
});

test('a sub path of an imported class appends only the remaining segments', function () {
    $map = Namespaces::buildNamespaceAliasMap(['App\\Models\\User']);

    expect(Namespaces::toFullyQualifiedClassName('User\\Profile', 'App\\Http', $map))
        ->toBe('App\\Models\\User\\Profile');
});

test('the current namespace is matched on a segment boundary', function () {
    // 'Application' merely starts with the text 'App'; it is not in the App namespace, so it has
    // to be resolved against it like any other unimported name.
    expect(Namespaces::toFullyQualifiedClassName('Application', 'App', []))->toBe('App\\Application')
        ->and(Namespaces::toFullyQualifiedClassName('App\\Models\\User', 'App', []))->toBe('App\\Models\\User')
        ->and(Namespaces::toFullyQualifiedClassName('App', 'App', []))->toBe('App');
});

test('an already qualified name inside an imported namespace is left alone', function () {
    $map = Namespaces::buildNamespaceAliasMap(['App\\Models\\User']);

    expect(Namespaces::toFullyQualifiedClassName('App\\Models\\User', 'Foo', $map))->toBe('App\\Models\\User');
});

test('an imported name is matched on a segment boundary too', function () {
    // 'App\Models\UserProfile' starts with the imported 'App\Models\User' as text only.
    $map = Namespaces::buildNamespaceAliasMap(['App\\Models\\User']);

    expect(Namespaces::toFullyQualifiedClassName('App\\Models\\UserProfile', 'Foo', $map))
        ->toBe('Foo\\App\\Models\\UserProfile');
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
