<?php declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

final readonly class BrandId implements IntId
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
