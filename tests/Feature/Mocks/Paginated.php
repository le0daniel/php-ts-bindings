<?php

declare(strict_types=1);

namespace Tests\Feature\Mocks;

/**
 * @template I
 */
final class Paginated
{
    /**
     * @param  list<I>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
    ) {
    }
}
