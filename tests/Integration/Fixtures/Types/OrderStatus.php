<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

/**
 * A pure unit enum: serializes by case name, the EnumNode default.
 */
enum OrderStatus
{
    case PENDING;
    case PAID;
    case SHIPPED;
    case CANCELLED;
}
