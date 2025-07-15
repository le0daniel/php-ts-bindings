<?php declare(strict_types=1);

namespace Tests\Feature\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;

/**
 * @phpstan-type SquareOptions array{dimensions: int, type: 'square'}
 * @phpstan-type CircleOptions array{radius: int, type: 'circle'}
 */
#[Castable]
final readonly class CreateObjectInput
{
    /**
     * @param string $name
     * @param SquareOptions|CircleOptions $options
     */
    public function __construct(
        public string $name,
        public array $options
    )
    {
    }
}