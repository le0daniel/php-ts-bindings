<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;

/**
 * The same two interfaces as AmbiguousId, but declaring #[Brand] locally settles it.
 */
#[Brand]
final readonly class DisambiguatedId implements AlsoBranded, IntId
{
    private function __construct(public int $value)
    {
    }

    public static function fromIntValue(int $value): static
    {
        return new self($value);
    }

    public function toIntValue(): int
    {
        return $this->value;
    }
}
