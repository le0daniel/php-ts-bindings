<?php

declare(strict_types=1);

namespace Tests\Unit\Reflection;

use Le0daniel\PhpTsBindings\Reflection\FileReflector;
use Tests\Unit\Reflection\Fixtures\ClassConstantBeforeDeclaration;

test('a ::class constant above the declaration is not mistaken for it', function () {
    // Discovery reflects every .php file under the discovery path, so one file shaped like this
    // used to abort discovery of the whole application.
    $reflector = new FileReflector(__DIR__.'/Fixtures/ClassConstantBeforeDeclaration.php');

    expect($reflector->getDeclaredClass()->getName())->toBe(ClassConstantBeforeDeclaration::class);
});

test('the namespace is read from the file', function () {
    $reflector = new FileReflector(__DIR__.'/Fixtures/ClassConstantBeforeDeclaration.php');

    expect($reflector->getDeclaredClass()->getNamespaceName())->toBe('Tests\\Unit\\Reflection\\Fixtures');
});
