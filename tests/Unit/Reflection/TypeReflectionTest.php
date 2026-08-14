<?php

namespace Tests\Unit\Reflection;

use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Helpers\ParsingScope;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Reflection\TypeReflector;
use ReflectionClass;
use Tests\Unit\Reflection\Mocks\NativeTypesMock;
use Tests\Unit\Reflection\Mocks\UserClassMock;

test('from reflection property', function () {
    $reflection = new ReflectionClass(UserClassMock::class);

    expect(TypeReflector::reflectProperty($reflection->getProperty('options')))
        ->toBe('array{isAdmin?: bool, isSuperAdmin?: bool}')
        ->and(TypeReflector::reflectProperty($reflection->getProperty('name')))
        ->toBe('non-empty-string')
        ->and(TypeReflector::reflectProperty($reflection->getProperty('birthdate')))
        ->toBe('\DateTimeInterface');

});
test('from reflection parameter', function () {

    $parameters = new ReflectionClass(UserClassMock::class)->getConstructor()->getParameters();

    expect(TypeReflector::reflectParameter($parameters[0]))
        ->toBe('non-empty-string')
        ->and(TypeReflector::reflectParameter($parameters[1]))
        ->toBe('\DateTimeInterface');
});

test('from reflection method', function () {

    $classReflection = new ReflectionClass(UserClassMock::class);

    expect(TypeReflector::reflectReturnType($classReflection->getMethod('toString')))
        ->toBe('non-empty-string')
        ->and(TypeReflector::reflectReturnType($classReflection->getMethod('toArray')))
        ->toBe('array');
});

test('multiline declarations from reflection', function () {
    $reflection = new ReflectionClass(UserClassMock::class);

    expect(TypeReflector::reflectProperty($reflection->getProperty('address')))
        ->toBe('array{ street: string, city: non-empty-string, }')
        ->and(TypeReflector::reflectProperty($reflection->getProperty('settings')))
        ->toBe('array{ theme: string, notifications: array{ email: bool, }, }')
        ->and(TypeReflector::reflectParameter($reflection->getConstructor()->getParameters()[2]))
        ->toBe('array{ theme: string, notifications: array{ email: bool, }, }')
        ->and(TypeReflector::reflectReturnType($reflection->getMethod('serialize')))
        ->toBe('array{ id: non-empty-string, roles: list<string>, }');
});

/**
 * PHP stringifies a reflection type with the leading backslash stripped, which is indistinguishable
 * from a name that still has to be resolved against the declaring file. Every class name coming out
 * of reflection is already fully qualified, so it is emitted as such.
 */
test('native class names are emitted fully qualified', function (string $method, string $expected) {
    $reflection = new ReflectionClass(NativeTypesMock::class);

    expect(TypeReflector::reflectReturnType($reflection->getMethod($method)))->toBe($expected);
})->with([
    'unimported class' => ['outOfNamespace', '\Tests\Mocks\Named\Conflict\Customer'],
    'nullable class' => ['nullableClass', '?\Tests\Mocks\Named\Conflict\Customer'],
    'explicit null union' => ['explicitNullUnion', '?\Tests\Mocks\Named\Conflict\Customer'],
    'union' => ['union', '\Tests\Mocks\Named\Customer|\Tests\Mocks\Named\Conflict\Customer'],
    'union with a builtin' => ['mixedUnion', '\Tests\Mocks\Named\Customer|string'],
    'intersection' => ['intersection', '\Countable&\Stringable'],
    // Reflection reports DNF as a union with an intersection member; without the parentheses the
    // string would read as `\Countable & (\Stringable|null)`.
    'disjunctive normal form' => ['disjunctiveNormalForm', '(\Countable&\Stringable)|null'],
    // self is resolved by PHP itself, so it arrives as a real class name.
    'self' => ['itself', '\Tests\Unit\Reflection\Mocks\NativeTypesMock'],
]);

test('builtin types are left untouched', function (string $method, string $expected) {
    $reflection = new ReflectionClass(NativeTypesMock::class);

    expect(TypeReflector::reflectReturnType($reflection->getMethod($method)))->toBe($expected);
})->with([
    'string' => ['builtin', 'string'],
    'nullable string' => ['nullableBuiltin', '?string'],
    // mixed and null report allowsNull() themselves; `?mixed` is not a type.
    'mixed' => ['anything', 'mixed'],
    'void' => ['nothing', 'void'],
]);

test('native property and parameter types are emitted fully qualified too', function () {
    $reflection = new ReflectionClass(NativeTypesMock::class);
    $parameters = $reflection->getMethod('parameters')->getParameters();

    expect(TypeReflector::reflectProperty($reflection->getProperty('unimported')))
        ->toBe('\DateTimeInterface')
        ->and(TypeReflector::reflectProperty($reflection->getProperty('imported')))
        ->toBe('\Tests\Mocks\Named\Conflict\Customer')
        ->and(TypeReflector::reflectProperty($reflection->getProperty('nullableClass')))
        ->toBe('?\Tests\Mocks\Named\Conflict\Customer')
        ->and(TypeReflector::reflectProperty($reflection->getProperty('builtin')))
        ->toBe('string')
        ->and(TypeReflector::reflectProperty($reflection->getProperty('nullableBuiltin')))
        ->toBe('?string')
        ->and(TypeReflector::reflectProperty($reflection->getProperty('anything')))
        ->toBe('mixed')
        ->and(TypeReflector::reflectParameter($parameters[0]))
        ->toBe('\Tests\Mocks\Named\Conflict\Customer')
        ->and(TypeReflector::reflectParameter($parameters[1]))
        ->toBe('?string');
});

/**
 * The regression this all exists for: a native return type whose class the declaring file does not
 * import used to be resolved a second time, producing
 * Tests\Unit\Reflection\Mocks\Tests\Mocks\Named\Conflict\Customer and a "No parser found." error.
 */
test('a native return type resolves without a PHPDoc to lean on', function () {
    $reflection = new ReflectionClass(NativeTypesMock::class);
    $scope = ParsingScope::fromReflectionClass($reflection);

    $node = new TypeParser()->parse(
        TypeReflector::reflectReturnType($reflection->getMethod('outOfNamespace')),
        $scope,
    );

    expect($node)->toBeInstanceOf(NodeInterface::class);
});
