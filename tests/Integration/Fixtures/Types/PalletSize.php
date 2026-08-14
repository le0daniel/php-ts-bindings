<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;

/**
 * The int twin of Currency: a backed enum opting into value object semantics, so it crosses the
 * wire as its backing int (2) instead of EnumNode's case-name default ("HALF"). An unknown int
 * makes self::from() throw a ValueError, which collapses to the generic validation.invalid_value.
 */
enum PalletSize: int implements IntValueObject
{
    case QUARTER = 1;
    case HALF = 2;
    case FULL = 3;

    public static function fromIntValue(int $value): static
    {
        return self::from($value);
    }

    public function toIntValue(): int
    {
        return $this->value;
    }
}
