<?php declare(strict_types=1);

namespace Tests\Mocks\Named;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;

/**
 * An enum's case union is identical in both directions, so #[Named] defaults to IO::BOTH here —
 * no explicit io needed.
 */
#[Named]
enum OrderStatus
{
    case OPEN;
    case SHIPPED;
}
