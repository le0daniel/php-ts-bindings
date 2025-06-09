<?php

namespace Tests\Unit\Utils;

use Le0daniel\PhpTsBindings\Utils\Reflections;
use ReflectionClass;
use Tests\Unit\Utils\Mocks\ReflectionsUtilMock;

test('get doc block extended type', function () {

    $reflectionClass = new ReflectionClass(ReflectionsUtilMock::class);

    expect(
        Reflections::getDocBlockExtendedType($reflectionClass->getProperty('name'))
    )->toBe('string');

    expect(
        Reflections::getDocBlockExtendedType($reflectionClass->getProperty('age'))
    )->toBe('array{amount: string, birthdate: \DateTime}');

    expect(
        Reflections::getDocBlockExtendedType(
            $reflectionClass->getConstructor()->getParameters()[2]
        )
    )->toBe('object{name: string, other: string}');

    expect(
        Reflections::getReturnType(
            $reflectionClass->getMethod('serialize')
        )
    )->toBe('array{string, int}');
});