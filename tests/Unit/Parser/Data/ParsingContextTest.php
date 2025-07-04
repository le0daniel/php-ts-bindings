<?php

namespace Tests\Unit\Parser\Data;

use Le0daniel\PhpTsBindings\Parser\Data\ParsingContext;
use ReflectionClass;
use Tests\Unit\Parser\Data\Stubs\ComplexPhpDoc;
use Tests\Unit\Parser\Data\Stubs\MyUserClass;

test('from class reflection', function () {
    // Reads all the context out of the file.
    $context = ParsingContext::fromClassReflection(new ReflectionClass(MyUserClass::class));
    $fromFileContext = ParsingContext::fromFilePath(__DIR__ . '/Stubs/MyUserClass.php');

    expect(serialize($context))
        ->toBe(serialize($fromFileContext))
        ->and($context->namespace)
        ->toBe('Tests\\Unit\\Parser\\Data\\Stubs')
        ->and($context->usedNamespaceMap)
        ->toBe([
            'Optimizer' => 'Le0daniel\PhpTsBindings\Parser\ASTOptimizer',
            'TypeParser' => 'Le0daniel\PhpTsBindings\Parser\TypeParser',
        ])
        ->and($context->localTypes)
        ->toBe([
            'UserWithData' => 'array{id: int, name: string, age: int, address: AddressInputData}',
        ])
        ->and($context->importedTypes)
        ->toEqual([
            'AddressInputData' => [
                'className' => 'Tests\Unit\Parser\Data\Stubs\Address',
                'typeName' => 'AddressInput',
            ],
            'ZIP' => [
                'className' => 'Tests\Unit\Parser\Data\Stubs\Address',
                'typeName' => 'ZIP',
            ],
        ]);
});

test("Extensive PHP Doc type declaration", function () {
    $fromFileContext = ParsingContext::fromClassString(ComplexPhpDoc::class);

    expect($fromFileContext->localTypes)->toBe([
        'ReadyToOrderInput' => 'array{     id: positive-int,     status: OrderStatus::READY_TO_ORDER,     fileId?: positive-int }',
        'WaitingOnApprovalInput' => 'array{     id: positive-int,     status: OrderStatus::WAITING_ON_APPROVAL }',
        'OrderedInput' => 'array{     id: positive-int,     status: OrderStatus::ORDERED }',
        'CompletedInput' => 'array{     id: positive-int,     status: OrderStatus::COMPLETED }',
        'RejectedInput' => 'array{     id: positive-int,     status: OrderStatus::REJECTED,     reason: string,     tips?: string|null }',
        'ChangeOrderStatusInput' => 'ReadyToOrderInput|WaitingOnApprovalInput|OrderedInput|CompletedInput|RejectedInput',
    ]);
});