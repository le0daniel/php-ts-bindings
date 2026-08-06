<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

final readonly class PlainId implements PlainContract
{
    private function __construct(public string $value)
    {
    }

    public static function fromStringValue(string $value): static
    {
        return new self($value);
    }

    public function toStringValue(): string
    {
        return $this->value;
    }
}
