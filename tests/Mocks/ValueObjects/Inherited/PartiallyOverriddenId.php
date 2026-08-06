<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;

/**
 * Declares only #[Brand] itself; #[Named] still comes from IntId.
 */
#[Brand('partialBrand')]
final readonly class PartiallyOverriddenId implements IntId
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
