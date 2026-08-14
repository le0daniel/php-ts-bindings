<?php

declare(strict_types=1);

namespace Tests\Mocks\Named;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;

/**
 * An enum's case union is identical in both directions, so the one alias #[Named] derives describes
 * both honestly.
 */
#[Named]
enum OrderStatus
{
    case OPEN;
    case SHIPPED;
}
