<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;

/**
 * CONSTRUCTOR strategy with promoted public params: every property is readable and writable
 * through the constructor, so the whole shape flows in BOTH directions.
 */
#[Castable(ObjectCastStrategy::CONSTRUCTOR)]
final readonly class Money
{
    public function __construct(
        public int $amount,
        public Currency $currency,
    ) {
    }
}
