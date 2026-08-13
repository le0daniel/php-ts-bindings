<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Operations;

use DateTimeImmutable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Tests\Integration\Fixtures\Types\Address;
use Tests\Integration\Fixtures\Types\Currency;
use Tests\Integration\Fixtures\Types\CustomerProfile;
use Tests\Integration\Fixtures\Types\Money;
use Tests\Integration\Fixtures\Types\OrderNumber;
use Tests\Integration\Fixtures\Types\OrderStatus;
use Tests\Integration\Fixtures\Types\OrderSummary;
use Tests\Integration\Fixtures\Types\Sku;

/**
 * Every handler is a pure function of its input - fixed dates, canned data - so the end-to-end
 * tests can assert the exact envelope JSON.
 */
final class OrderQueries
{
    /**
     * Deep nesting: castable output classes, an ATOM DateTimeImmutable, a value-object enum, a
     * unit enum and a non-empty list, all in one response.
     *
     * @param  array{orderNumber: non-empty-string}  $input
     * @return array{
     *     createdAt: DateTimeImmutable,
     *     currency: Currency,
     *     items: non-empty-list<array{lineTotal: Money, quantity: int, sku: string}>,
     *     shippingAddress: Address,
     *     status: OrderStatus,
     *     total: Money,
     * }
     */
    #[Query('orders')]
    public function getOrder(array $input): array
    {
        $address = new Address();
        $address->city = 'Zurich';
        $address->street = 'Bahnhofstrasse 1';
        $address->zip = '8001';

        return [
            'createdAt' => new DateTimeImmutable('2024-05-01T12:00:00+00:00'),
            'currency' => Currency::CHF,
            'items' => [
                ['lineTotal' => new Money(1000, Currency::CHF), 'quantity' => 2, 'sku' => 'ABC-123'],
                ['lineTotal' => new Money(1495, Currency::CHF), 'quantity' => 1, 'sku' => 'XYZ-999'],
            ],
            'shippingAddress' => $address,
            'status' => OrderStatus::PAID,
            'total' => new Money(2495, Currency::CHF),
        ];
    }

    /**
     * Optional docblock keys with handler-side defaults; the target for query input coercion.
     *
     * @param  array{page?: positive-int, perPage?: int<1, 100>}  $input
     * @return array{orders: list<array{orderNumber: string, status: OrderStatus}>, page: int, perPage: int}
     */
    #[Query('orders')]
    public function listOrders(array $input): array
    {
        return [
            'orders' => [
                ['orderNumber' => 'ORD-1001', 'status' => OrderStatus::PAID],
                ['orderNumber' => 'ORD-1002', 'status' => OrderStatus::PENDING],
            ],
            'page' => $input['page'] ?? 1,
            'perPage' => $input['perPage'] ?? 20,
        ];
    }

    /**
     * Records on the way out: a closed literal key set, and an empty record that must serialize
     * as {} and never degrade to [].
     *
     * @return array{counts: array<'PAID'|'PENDING'|'SHIPPED', int>, emptyByDay: array<string, int>}
     */
    #[Query('orders')]
    public function statusCounts(null $input): array
    {
        return [
            'counts' => ['PAID' => 2, 'PENDING' => 1, 'SHIPPED' => 0],
            'emptyByDay' => [],
        ];
    }

    /**
     * Discriminated union on the OUTPUT side: three inline shapes sharing the literal kind.
     *
     * @param  array{stage: 'created'|'delivered'|'shipped'}  $input
     * @return array{at: DateTimeString<'Y-m-d'>, kind: 'created'}|array{carrier: string, kind: 'shipped', trackingCode: string}|array{kind: 'delivered', signedBy: string|null}
     */
    #[Query('orders')]
    public function trackingEvent(array $input): array
    {
        return match ($input['stage']) {
            'created' => ['at' => new DateTimeImmutable('2024-05-01T12:00:00+00:00'), 'kind' => 'created'],
            'shipped' => ['carrier' => 'DHL', 'kind' => 'shipped', 'trackingCode' => 'JJD-0003-9000-7882'],
            'delivered' => ['kind' => 'delivered', 'signedBy' => null],
        };
    }

    /**
     * Undiscriminated union input: a free-text branch and a struct branch, resolved by
     * first-match probing.
     *
     * @param  array{filter: string|array{status: OrderStatus}}  $input
     * @return list<string>
     */
    #[Query('orders')]
    public function searchOrders(array $input): array
    {
        $filter = $input['filter'];
        if (is_string($filter)) {
            return ['ORD-1001', 'ORD-1003'];
        }

        return ['ORD-BY-STATUS-'.$filter['status']->name];
    }

    /**
     * A string value object with a non-ValidationException rejection, and a bare scalar as the
     * envelope data.
     *
     * @param  array{orderNumber: OrderNumber}  $input
     * @return string
     */
    #[Query('orders')]
    public function invoiceFileName(array $input): string
    {
        return 'invoice-'.$input['orderNumber']->toStringValue().'.pdf';
    }

    /**
     * An output-only class (no Castable attribute) as the declared return type.
     *
     * @param  array{orderNumber: non-empty-string}  $input
     */
    #[Query('orders')]
    public function orderSummary(array $input): OrderSummary
    {
        return new OrderSummary(
            orderNumber: $input['orderNumber'],
            itemCount: 3,
            total: new Money(2495, Currency::CHF),
            status: OrderStatus::SHIPPED,
        );
    }

    /**
     * Pick and Omit projections over a class while the handler returns full instances.
     *
     * @return array{card: Pick<CustomerProfile, 'email'|'name'>, publicCard: Omit<CustomerProfile, 'notes'>}
     */
    #[Query('orders')]
    public function customerSnapshot(null $input): array
    {
        $profile = new CustomerProfile(
            email: 'ada@example.com',
            name: 'Ada',
            notes: 'internal only',
            tier: 'gold',
        );

        return ['card' => $profile, 'publicCard' => $profile];
    }

    /**
     * A tuple with exact arity, plus a Sku round-trip from input back into the output.
     *
     * @param  array{sku: Sku}  $input
     * @return array{dimensionsMm: array{int, int, int}, sku: string}
     */
    #[Query('orders')]
    public function parcelDimensions(array $input): array
    {
        return [
            'dimensionsMm' => [300, 200, 50],
            'sku' => $input['sku']->toStringValue(),
        ];
    }

    /**
     * Branded virtual types: pure codegen metadata, plain string and int at runtime.
     *
     * @return array{customerId: BrandedInt<'customerId'>, orderId: BrandedString<'orderId'>}
     */
    #[Query('orders')]
    public function sessionRefs(null $input): array
    {
        return ['customerId' => 512, 'orderId' => 'ORD-1001'];
    }
}
