<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;

/**
 * One arm of an undiscriminated union of castable classes. Its property names are deliberately
 * disjoint from HomeDelivery's, so first-match probing resolves the arm unambiguously.
 */
#[Castable(ObjectCastStrategy::CONSTRUCTOR)]
final readonly class PickupPoint
{
    public function __construct(
        public string $locationCode,
    ) {
    }
}
