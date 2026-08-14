<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

/**
 * An int-backed enum that is NOT a value object: like PaymentMethod for strings, the backing
 * ints stay invisible and the case names ("LOW") are what the client sends and receives.
 */
enum StockLevel: int
{
    case NONE = 0;
    case LOW = 1;
    case FULL = 2;
}
