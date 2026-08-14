<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;

/**
 * The other arm of the castable union next to PickupPoint, with disjoint property names.
 */
#[Castable(ObjectCastStrategy::CONSTRUCTOR)]
final readonly class HomeDelivery
{
    public function __construct(
        public string $street,
        public string $zip,
    ) {
    }
}
