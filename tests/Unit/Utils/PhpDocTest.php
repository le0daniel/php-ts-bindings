<?php

namespace Tests\Unit\Utils;

use Le0daniel\PhpTsBindings\Utils\PhpDoc;

test('normalize', function () {

    expect(PhpDoc::normalize(" /** @var string */"))->toBe(' @var string');
    expect(PhpDoc::normalize(<<<DOC
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