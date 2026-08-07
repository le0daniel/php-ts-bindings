<?php

namespace Tests\Unit\Utils;

use Le0daniel\PhpTsBindings\Utils\Namespaces;

/**
 * The maps under test are built from a file's `use` statements, so every case here feeds
 * toFullyQualifiedClassName() a map that buildNamespaceAliasMap() can actually produce. A
 * hand-written map that cannot occur is how the doubled-segment bug stayed hidden.
 *
 * These are PHP's name resolution rules and nothing more, matching PHPStan's
 * NameScope::resolveStringName(): a leading backslash means absolute, a first segment matching an
 * import is substituted, and anything else is relative to the current namespace. Reflection-derived
 * names never reach here without a leading backslash - TypeReflector puts one on.
 */
test('a leading backslash means the name is already fully qualified', function () {
    expect(Namespaces::toFullyQualifiedClassName('\\Bar', 'Foo', []))->toBe('Bar');
});

test('an unimported name is resolved against the current namespace', function () {
    expect(Namespaces::toFullyQualifiedClassName('Bar', 'Foo', []))->toBe('Foo\\Bar');
});

test('an unimported name in the global namespace is left alone', function () {
    expect(Namespaces::toFullyQualifiedClassName('Bar', null, []))->toBe('Bar');
});

test('an imported class resolves to what it was imported as', function () {
    $map = Namespaces::buildNamespaceAliasMap(['App\\Utils\\MyClass']);

    expect(Namespaces::toFullyQualifiedClassName('MyClass', 'Foo', $map))->toBe('App\\Utils\\MyClass');
});

test('an aliased import resolves to the aliased class', function () {
    $map = Namespaces::buildNamespaceAliasMap(['App\\Contracts\\User' => 'UserContract']);

    expect(Namespaces::toFullyQualifiedClassName('UserContract', 'Foo', $map))->toBe('App\\Contracts\\User');
});

test('imports are matched case insensitively, as PHP resolves them', function () {
    $map = Namespaces::buildNamespaceAliasMap(['App\\Utils\\MyClass', 'App\\Contracts\\User' => 'UserContract']);

    expect(Namespaces::toFullyQualifiedClassName('myclass', 'Foo', $map))->toBe('App\\Utils\\MyClass')
        ->and(Namespaces::toFullyQualifiedClassName('USERCONTRACT', 'Foo', $map))->toBe('App\\Contracts\\User');
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

/**
 * Only the FIRST segment is ever matched against the imports; a qualified name whose first segment
 * is not imported is relative, however fully qualified it looks. Guessing otherwise is what made
 * `Tests\Mocks\Named\Customer` resolve and `Tests\Mocks\Named\Conflict\Customer` fail in the same
 * file, purely because of which one happened to sit under an import.
 */
test('a qualified name whose first segment is not imported is still relative', function () {
    $map = Namespaces::buildNamespaceAliasMap(['App\\Models\\User']);

    expect(Namespaces::toFullyQualifiedClassName('App\\Models\\User', 'Foo', $map))
        ->toBe('Foo\\App\\Models\\User')
        ->and(Namespaces::toFullyQualifiedClassName('App\\Models\\UserProfile', 'Foo', $map))
        ->toBe('Foo\\App\\Models\\UserProfile');
});

test('the current namespace is prefixed even when the name already sits below it', function () {
    expect(Namespaces::toFullyQualifiedClassName('Application', 'App', []))->toBe('App\\Application')
        ->and(Namespaces::toFullyQualifiedClassName('App\\Models\\User', 'App', []))
        ->toBe('App\\App\\Models\\User')
        ->and(Namespaces::toFullyQualifiedClassName('App', 'App', []))->toBe('App\\App');
});

test('build namespace alias map', function () {
    expect(Namespaces::buildNamespaceAliasMap([]))->toBe([]);

    $namespaces = [
        'App\\Models\\User',
        'App\\Services\\PaymentService' => 'Payments',
        '\\App\\Utils\\Strings',
        '\\App\\Utils\\Arrays' => 'Arr',
    ];

    // Keys are lowercased: PHP resolves `use` aliases case insensitively.
    $expectedMap = [
        'user' => 'App\\Models\\User',
        'payments' => 'App\\Services\\PaymentService',
        'strings' => 'App\\Utils\\Strings',
        'arr' => 'App\\Utils\\Arrays',
    ];

    expect(Namespaces::buildNamespaceAliasMap($namespaces))->toEqual($expectedMap);
});
