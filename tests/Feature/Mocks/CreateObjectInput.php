<?php declare(strict_types=1);

namespace Tests\Feature\Mocks;

/**
 * @phpstan-type SquareOptions array{dimensions: int, type: 'square'}
 * @phpstan-type CircleOptions array{radius: int, type: 'circle'}
 */
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