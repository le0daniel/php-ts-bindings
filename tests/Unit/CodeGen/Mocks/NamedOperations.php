<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Tests\Mocks\Named\Order;
use Tests\Mocks\Named\OrderStatus;
use Tests\Mocks\ValueObjects\UserId;

/**
 * Operations whose schemas use named types: Order contains the named Customer and a branded
 * UserId, OrderStatus is a named enum used in both directions and by both operations.
 */
final class NamedOperations
{
    /**
     * @param array{status: OrderStatus} $input
     * @return Order
     */
    #[Query('orders')]
    public function get(array $input): Order
    {
        return new Order();
    }

    /**
     * @param array{id: UserId} $input
     * @return array{status: OrderStatus}
     */
    #[Query('orders')]
    public function status(array $input): array
    {
        return ['status' => OrderStatus::OPEN];
    }
}
