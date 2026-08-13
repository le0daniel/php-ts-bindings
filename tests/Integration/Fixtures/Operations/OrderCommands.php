<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Operations;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Tests\Integration\Fixtures\Exceptions\OrderAlreadyShippedException;
use Tests\Integration\Fixtures\Exceptions\OrderNotFoundException;
use Tests\Integration\Fixtures\Types\Address;
use Tests\Integration\Fixtures\Types\LineItemInput;
use Tests\Integration\Fixtures\Types\Money;
use Tests\Integration\Fixtures\Types\OrderStatus;
use Tests\Integration\Fixtures\Types\PlaceOrderInput;

final class OrderCommands
{
    /**
     * A castable class as the native input parameter: hydrates the whole nested graph including
     * value objects and the ASSIGN_PROPERTIES address. Totals derive from the input only.
     *
     * @return array{itemCount: int, orderNumber: string, status: OrderStatus, total: Money}
     */
    #[Command('orders')]
    public function placeOrder(PlaceOrderInput $input): array
    {
        $units = array_sum(array_map(
            static fn (LineItemInput $item): int => $item->quantity->toIntValue(),
            $input->items,
        ));

        return [
            'itemCount' => count($input->items),
            'orderNumber' => 'ORD-NEW-1',
            'status' => OrderStatus::PENDING,
            'total' => new Money($units * 250, $input->currency),
        ];
    }

    /**
     * ASSIGN_PROPERTIES in both directions: the parsed Address is returned as-is, so an omitted
     * Optional company comes back as null.
     *
     * @param  array{address: Address, orderNumber: non-empty-string}  $input
     */
    #[Command('orders')]
    public function updateShippingAddress(array $input): Address
    {
        return $input['address'];
    }

    /**
     * A declared domain exception surfaces as DOMAIN_ERROR with its registered name.
     *
     * @param  array{orderNumber: non-empty-string}  $input
     * @return array{cancelled: true, orderNumber: string}
     */
    #[Command('orders')]
    #[Throws(OrderAlreadyShippedException::class, name: 'order_already_shipped')]
    public function cancelOrder(array $input): array
    {
        if ($input['orderNumber'] === 'ORD-SHIPPED') {
            throw new OrderAlreadyShippedException('Order has already been shipped');
        }

        return ['cancelled' => true, 'orderNumber' => $input['orderNumber']];
    }

    /**
     * A Throws-typed exception maps onto the finite error catalogue: NOT_FOUND, no details.
     *
     * @param  array{amount: Money, orderNumber: non-empty-string}  $input
     * @return array{refund: Money, status: 'refunded'}
     */
    #[Command('orders')]
    #[Throws(OrderNotFoundException::class, type: ErrorType::NOT_FOUND)]
    public function requestRefund(array $input): array
    {
        if ($input['orderNumber'] === 'ORD-MISSING') {
            throw new OrderNotFoundException("No such order: {$input['orderNumber']}");
        }

        return ['refund' => $input['amount'], 'status' => 'refunded'];
    }

    /**
     * Deliberately violates its declared output type: the server must answer INTERNAL_ERROR and
     * leak nothing about the payload.
     *
     * @param  array{payload: string}  $input
     * @return array{processed: array{id: int}}
     */
    #[Command('orders')]
    public function recordPaymentWebhook(array $input): array
    {
        /** @phpstan-ignore-next-line */
        return ['processed' => ['id' => 'not-an-int']];
    }

    /**
     * The attribute name overrides the method name: reachable as orders.archive only.
     *
     * @param  array{orderNumber: non-empty-string}  $input
     * @return array{archived: bool, orderNumber: string}
     */
    #[Command('orders', name: 'archive')]
    public function archiveOrder(array $input): array
    {
        return ['archived' => true, 'orderNumber' => $input['orderNumber']];
    }

    /**
     * A record as the whole input, echoed back: enum cases in, case names out, {} stays {}.
     *
     * @param  array<string, OrderStatus>  $input
     * @return array{updated: array<string, OrderStatus>}
     */
    #[Command('orders')]
    public function bulkUpdateStatus(array $input): array
    {
        return ['updated' => $input];
    }
}
