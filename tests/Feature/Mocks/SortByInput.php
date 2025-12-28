<?php declare(strict_types=1);

namespace Tests\Feature\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;

/**
 * @template-covariant T of string
 */
#[Castable(ObjectCastStrategy::CONSTRUCTOR)]
final readonly class SortByInput
{
    /**
     * @param T $by
     * @param "asc"|"desc" $direction
     */
    public function __construct(
        public string $by,
        public string $direction,
    )
    {
    }

    /**
     * @param list<string> $columns
     */
    public function assertValidColumn(array $columns): void
    {
        if (!in_array($this->by, $columns, true)) {
            throw new \InvalidArgumentException('Invalid field: ' . $this->by);
        }
    }
}