<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;

/**
 * A generic castable container: Batch<Sku> in an operation signature binds T for this class's
 * own constructor docblock. Generic arguments do not propagate into nested classes, so T is
 * used directly on the constructor parameter and nowhere deeper.
 *
 * @template T
 */
#[Castable(ObjectCastStrategy::CONSTRUCTOR)]
final readonly class Batch
{
    /**
     * @param  list<T>  $items
     */
    public function __construct(
        public int $count,
        public array $items,
    ) {
    }
}
