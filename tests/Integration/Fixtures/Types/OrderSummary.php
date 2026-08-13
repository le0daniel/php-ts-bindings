<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

/**
 * Deliberately NOT Castable: an output-only class (NEVER strategy). All public properties
 * serialize; using it as an input type would be rejected at parse time.
 */
final readonly class OrderSummary
{
    public function __construct(
        public string $orderNumber,
        public int $itemCount,
        public Money $total,
        public OrderStatus $status,
    ) {
    }
}
