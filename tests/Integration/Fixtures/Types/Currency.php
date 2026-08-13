<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

use Le0daniel\PhpTsBindings\Contracts\ValueObjects\StringValueObject;

/**
 * A backed enum that opts into value object semantics, so it goes over the wire as its lowercase
 * backing value ("chf") instead of EnumNode's case-name default ("CHF").
 */
enum Currency: string implements StringValueObject
{
    case CHF = 'chf';
    case EUR = 'eur';
    case USD = 'usd';

    public static function fromStringValue(string $value): static
    {
        return self::from($value);
    }

    public function toStringValue(): string
    {
        return $this->value;
    }
}
