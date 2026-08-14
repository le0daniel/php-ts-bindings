<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

/**
 * Inherits #[Brand] and #[Named] from IntId and derives both from its own name.
 *
 * Note: Tests\Mocks\Named\NamedValueObject also resolves to the alias `AccountId`, but as a string
 * backed value object. Emitting both through one shared AliasRegistry is a conflicting alias by
 * design — keep them out of the same generation run.
 */
final readonly class AccountId implements IntId
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
