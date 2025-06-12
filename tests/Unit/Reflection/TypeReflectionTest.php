<?php

namespace Tests\Unit\Reflection;

use Le0daniel\PhpTsBindings\Reflection\TypeReflector;
use ReflectionClass;
use Tests\Unit\Reflection\Mocks\UserClassMock;

test('from reflection property', function () {
    $reflection = new ReflectionClass(UserClassMock::class);

    expect(TypeReflector::reflectProperty($reflection->getProperty('options')))
        ->toBe('array{isAdmin?: bool, isSuperAdmin?: bool}')
        ->and(TypeReflector::reflectProperty($reflection->getProperty('name')))
        ->toBe('non-empty-string')
        ->and(TypeReflector::reflectProperty($reflection->getProperty('birthdate')))
        ->toBe('DateTimeInterface');

});
test('from reflection parameter', function () {

    $parameters = new ReflectionClass(UserClassMock::class)->getConstructor()->getParameters();

    expect(TypeReflector::reflectParameter($parameters[0]))
        ->toBe('non-empty-string')
        ->and(TypeReflector::reflectParameter($parameters[1]))
        ->toBe('DateTimeInterface');
});

test('from reflection method', function () {

    $classReflection = new ReflectionClass(UserClassMock::class);

    expect(TypeReflector::reflectReturnType($classReflection->getMethod('toString')))
        ->toBe('non-empty-string')
        ->and(TypeReflector::reflectReturnType($classReflection->getMethod('toArray')))
        ->toBe('array');
});