<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;

/**
 * A class used directly as an operation input parameter: a CONSTRUCTOR castable nesting an
 * ASSIGN_PROPERTIES castable (Address) and a non-empty list of further castables.
 */
#[Castable(ObjectCastStrategy::CONSTRUCTOR)]
final readonly class PlaceOrderInput
{
    /**
     * @param  non-empty-list<LineItemInput>  $items
     */
    public function __construct(
        public Currency $currency,
        public array $items,
        public Address $shippingAddress,
    ) {
    }
}
