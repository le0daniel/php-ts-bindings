<?php

namespace Tests\Unit\Parser\Data;

use Le0daniel\PhpTsBindings\Parser\Data\ParsingContext;
use ReflectionClass;
use Tests\Unit\Parser\Data\Stubs\MyUserClass;

test('from class reflection', function () {
    // Reads all the context out of the file.
    $context = ParsingContext::fromClassReflection(new ReflectionClass(MyUserClass::class));
    $fromFileContext = ParsingContext::fromFilePath(__DIR__ . '/Stubs/MyUserClass.php');

    expect(serialize($context))->toBe(serialize($fromFileContext));

    expect($context->namespace)
        ->toBe('Tests\\Unit\\Parser\\Data\\Stubs')
        ->and($context->usedNamespaceMap)
        ->toBe([
            'Optimizer' => 'Le0daniel\PhpTsBindings\Parser\ASTOptimizer',
            'TypeParser' => 'Le0daniel\PhpTsBindings\Parser\TypeParser',
        ])
        ->and($context->localTypes)
        ->toBe([
            'UserWithData' => 'array{id: int, name: string, age: int, address: AddressInput}',
        ])
        ->and($context->importedTypes)
        ->toBe([
            'AddressInput' => [
                'className' => 'Tests\Unit\Parser\Data\Stubs\Address',
                'typeName' => 'Address',
            ],
            'ZIP' => [
                'className' => 'Tests\Unit\Parser\Data\Stubs\Address',
                'typeName' => 'ZIP',
            ],
        ]);;
});