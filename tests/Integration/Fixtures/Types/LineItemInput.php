<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Optional;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;

/**
 * Nested value objects inside a castable: a rejection inside sku or quantity has to surface at a
 * dot-joined path like items.0.sku. The Optional promoted param falls back to null when omitted.
 */
#[Castable(ObjectCastStrategy::CONSTRUCTOR)]
final readonly class LineItemInput
{
    public function __construct(
        public Sku $sku,
        public Quantity $quantity,
        #[Optional]
        public ?string $note = null,
    ) {
    }
}
