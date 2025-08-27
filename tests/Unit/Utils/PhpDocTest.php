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