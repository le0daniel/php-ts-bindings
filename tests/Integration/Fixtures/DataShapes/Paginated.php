<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\DataShapes;

/**
 * @template T
 */
final class Paginated
{
    public bool $hasNextPage {
        get => $this->currentPage * $this->perPage < $this->total;
    }

    public bool $hasPreviousPage {
        get => $this->currentPage > 1;
    }

    /**
     * @param list<T> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $currentPage,
        public readonly int $perPage,
    ) {
    }
}
