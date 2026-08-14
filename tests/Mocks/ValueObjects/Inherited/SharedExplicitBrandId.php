<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

final readonly class SharedExplicitBrandId implements SharedExplicitBrand
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
