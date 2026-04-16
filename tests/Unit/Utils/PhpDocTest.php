<?php

namespace Tests\Unit\Utils;

use Le0daniel\PhpTsBindings\Utils\PhpDoc;

test('normalize', function () {

    expect(PhpDoc::normalize(" /** @var string */"))->toBe(' @var string')
        ->and(PhpDoc::normalize(<<<DOC
/**
 * @phpstan-type ReadyToOrderInput array{
 *     id: positive-int,
 *     status: OrderStatus::READY_TO_ORDER,
 *     fileId?: positive-int
 * }
 *
 * @phpstan-type WaitingOnApprovalInput array{
 *     id: positive-int,
 *     status: OrderStatus::WAITING_ON_APPROVAL
 * }
 *
 * @phpstan-type OrderedInput array{
 *     id: positive-int,
 *     status: OrderStatus::ORDERED
 * }
 *
 * @phpstan-type CompletedInput array{
 *     id: positive-int,
 *     status: OrderStatus::COMPLETED
 * }
 *
 * @phpstan-type RejectedInput array{
 *     id: positive-int,
 *     status: OrderStatus::REJECTED,
 *     reason: string,
 *     tips?: string|null
 * }
 *
 * @phpstan-type ChangeOrderStatusInput ReadyToOrderInput|WaitingOnApprovalInput|OrderedInput|CompletedInput|RejectedInput
 */
DOC
        ))->toBe(<<<TEXT

@phpstan-type ReadyToOrderInput array{
    id: positive-int,
    status: OrderStatus::READY_TO_ORDER,
    fileId?: positive-int
}
@phpstan-type WaitingOnApprovalInput array{
    id: positive-int,
    status: OrderStatus::WAITING_ON_APPROVAL
}
@phpstan-type OrderedInput array{
    id: positive-int,
    status: OrderStatus::ORDERED
}
@phpstan-type CompletedInput array{
    id: positive-int,
    status: OrderStatus::COMPLETED
}
@phpstan-type RejectedInput array{
    id: positive-int,
    status: OrderStatus::REJECTED,
    reason: string,
    tips?: string|null
}
@phpstan-type ChangeOrderStatusInput ReadyToOrderInput|WaitingOnApprovalInput|OrderedInput|CompletedInput|RejectedInput

TEXT
        );
});

test('Find local defined types', function () {
    expect(PhpDoc::findImportedTypeDefinition(<<<DOC
/**
 * @phpstan-import-type MyType from OtherClass
 * @phpstan-import-type MyType from OtherClass as Other
 */
DOC
    ))->toEqual([
        'MyType' => [
            'className' => 'OtherClass',
            'typeName' => 'MyType',
        ],
        'Other' => [
            'className' => 'OtherClass',
            'typeName' => 'MyType',
        ],
    ]);
});

test("find locally defined types", function () {
    expect(PhpDoc::findLocallyDefinedTypes(<<<DOC
/**
 * @phpstan-type MyType object{id: ID}
 * @phpstan-type Other array{id: string}
 * @phpstan-type MultiLine array{
 *    id: string,
 *    status: OrderStatus::READY_TO_ORDER,
 * }
 */
DOC
    ))->toEqual([
        'MyType' => 'object{id: ID}',
        'Other' => 'array{id: string}',
        'MultiLine' => 'array{    id: string,    status: OrderStatus::READY_TO_ORDER, }'
    ]);
});

test('Find Generics', function () {
    expect(PhpDoc::findGenerics(<<<DOC
/**
 * @template T of OtherClass
 * @template O 
 */
DOC
    ))->toEqual(['T', 'O']);
});

test('Find Generics with covariant', function () {
    expect(PhpDoc::findGenerics(<<<DOC
/**
 * @template-covariant T of OtherClass
 * @template O
 */
DOC
    ))->toEqual(['T', 'O']);
});

test('findParamWithNameDeclaration: single line simple', function () {
    expect(PhpDoc::findParamWithNameDeclaration(<<<'DOC'
/**
 * @param string $name
 */
DOC, 'name'))->toBe('string');
});

test('findParamWithNameDeclaration: single line complex type', function () {
    expect(PhpDoc::findParamWithNameDeclaration(<<<'DOC'
/**
 * @param array{key: "string", other: int} $data
 */
DOC, 'data'))->toBe('array{key: "string", other: int}');
});

test('findParamWithNameDeclaration: multiline type', function () {
    expect(PhpDoc::findParamWithNameDeclaration(<<<'DOC'
/**
 * @param array{
 *     key: "string"
 * } $docBlocks
 */
DOC, 'docBlocks'))->toBe('array{ key: "string" }');
});

test('findParamWithNameDeclaration: multiline type with param name on separate line', function () {
    expect(PhpDoc::findParamWithNameDeclaration(<<<'DOC'
/**
 * @param array{
 *     key: "string"
 * }
 *      $docBlocks
 */
DOC, 'docBlocks'))->toBe('array{ key: "string" }');
});

test('findParamWithNameDeclaration: multiple params selects correct one', function () {
    $doc = <<<'DOC'
/**
 * @param string $first
 * @param array{
 *     key: "string"
 * } $second
 * @param int $third
 */
DOC;

    expect(PhpDoc::findParamWithNameDeclaration($doc, 'first'))->toBe('string')
        ->and(PhpDoc::findParamWithNameDeclaration($doc, 'second'))->toBe('array{ key: "string" }')
        ->and(PhpDoc::findParamWithNameDeclaration($doc, 'third'))->toBe('int');
});

test('findParamWithNameDeclaration: not found returns null', function () {
    expect(PhpDoc::findParamWithNameDeclaration(<<<'DOC'
/**
 * @param string $name
 */
DOC, 'other'))->toBeNull();
});

test('findParamWithNameDeclaration: empty inputs return null', function () {
    expect(PhpDoc::findParamWithNameDeclaration(null, 'name'))->toBeNull()
        ->and(PhpDoc::findParamWithNameDeclaration(false, 'name'))->toBeNull()
        ->and(PhpDoc::findParamWithNameDeclaration('', 'name'))->toBeNull();
});

test('findParamWithNameDeclaration: does not match partial param name', function () {
    expect(PhpDoc::findParamWithNameDeclaration(<<<'DOC'
/**
 * @param string $nameOther
 */
DOC, 'name'))->toBeNull();
});

test('findReturnTypeDeclaration: single line', function () {
    expect(PhpDoc::findReturnTypeDeclaration(<<<'DOC'
/**
 * @return string
 */
DOC))->toBe('string');
});

test('findReturnTypeDeclaration: complex single line', function () {
    expect(PhpDoc::findReturnTypeDeclaration(<<<'DOC'
/**
 * @return array{string, int}
 */
DOC))->toBe('array{string, int}');
});

test('findReturnTypeDeclaration: multiline', function () {
    expect(PhpDoc::findReturnTypeDeclaration(<<<'DOC'
/**
 * @return array{
 *     key: string,
 *     other: int
 * }
 */
DOC))->toBe('array{ key: string, other: int }');
});

test('findReturnTypeDeclaration: followed by another tag', function () {
    expect(PhpDoc::findReturnTypeDeclaration(<<<'DOC'
/**
 * @return string
 * @throws Exception
 */
DOC))->toBe('string');
});

test('findReturnTypeDeclaration: multiline followed by another tag', function () {
    expect(PhpDoc::findReturnTypeDeclaration(<<<'DOC'
/**
 * @return array{
 *     key: string
 * }
 * @throws Exception
 */
DOC))->toBe('array{ key: string }');
});

test('findReturnTypeDeclaration: inline docblock', function () {
    expect(PhpDoc::findReturnTypeDeclaration('/** @return string */'))->toBe('string');
});

test('findReturnTypeDeclaration: empty inputs return null', function () {
    expect(PhpDoc::findReturnTypeDeclaration(null))->toBeNull()
        ->and(PhpDoc::findReturnTypeDeclaration(false))->toBeNull()
        ->and(PhpDoc::findReturnTypeDeclaration(''))->toBeNull();
});

test('findReturnTypeDeclaration: not found returns null', function () {
    expect(PhpDoc::findReturnTypeDeclaration(<<<'DOC'
/**
 * @param string $name
 */
DOC))->toBeNull();
});

test('findFirstVarDeclaration: simple', function () {
    expect(PhpDoc::findFirstVarDeclaration(<<<'DOC'
/**
 * @var string
 */
DOC))->toBe('string');
});

test('findFirstVarDeclaration: with variable name', function () {
    expect(PhpDoc::findFirstVarDeclaration(<<<'DOC'
/**
 * @var string $myVar
 */
DOC))->toBe('string');
});

test('findFirstVarDeclaration: complex single line', function () {
    expect(PhpDoc::findFirstVarDeclaration(<<<'DOC'
/**
 * @var array{isAdmin?: bool}
 */
DOC))->toBe('array{isAdmin?: bool}');
});

test('findFirstVarDeclaration: multiline', function () {
    expect(PhpDoc::findFirstVarDeclaration(<<<'DOC'
/**
 * @var array{
 *     key: string,
 *     other: int
 * }
 */
DOC))->toBe('array{ key: string, other: int }');
});

test('findFirstVarDeclaration: multiline with variable name', function () {
    expect(PhpDoc::findFirstVarDeclaration(<<<'DOC'
/**
 * @var array{
 *     key: string
 * } $myVar
 */
DOC))->toBe('array{ key: string }');
});

test('findFirstVarDeclaration: multiline with variable on separate line', function () {
    expect(PhpDoc::findFirstVarDeclaration(<<<'DOC'
/**
 * @var array{
 *     key: string
 * }
 * $myVar
 */
DOC))->toBe('array{ key: string }');
});

test('findFirstVarDeclaration: followed by another tag', function () {
    expect(PhpDoc::findFirstVarDeclaration(<<<'DOC'
/**
 * @var array{
 *     key: string
 * }
 * @deprecated
 */
DOC))->toBe('array{ key: string }');
});

test('findFirstVarDeclaration: inline docblock', function () {
    expect(PhpDoc::findFirstVarDeclaration('/** @var string|int */'))->toBe('string|int');
});

test('findFirstVarDeclaration: empty inputs return null', function () {
    expect(PhpDoc::findFirstVarDeclaration(null))->toBeNull()
        ->and(PhpDoc::findFirstVarDeclaration(false))->toBeNull()
        ->and(PhpDoc::findFirstVarDeclaration(''))->toBeNull();
});

test('findFirstVarDeclaration: not found returns null', function () {
    expect(PhpDoc::findFirstVarDeclaration(<<<'DOC'
/**
 * @return string
 */
DOC))->toBeNull();
});