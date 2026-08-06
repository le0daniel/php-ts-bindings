<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects;

use Le0daniel\PhpTsBindings\Contracts\ValueObjects\StringValueObject;

/**
 * A backed enum that opts into value object semantics, so it serializes by backing value
 * instead of EnumNode's case-name default. Also the reason ValueObjectNode must catch
 * Throwable and not Exception: self::from() throws \ValueError, which extends Error.
 */
enum StatusEnum: string implements StringValueObject
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public static function fromStringValue(string $value): static
    {
        return self::from($value);
    }

    public function toStringValue(): string
    {
        return $this->value;
    }
}
