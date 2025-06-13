<?php declare(strict_types=1);

namespace Tests\Unit\Parser\Data\Stubs;

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
final class ComplexPhpDoc
{

}