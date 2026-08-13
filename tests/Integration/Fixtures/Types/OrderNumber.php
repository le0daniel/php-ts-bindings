<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

use Le0daniel\PhpTsBindings\Contracts\ValueObjects\StringValueObject;
use RuntimeException;

/**
 * Rejects with a plain RuntimeException, NOT a ValidationException: the internal message must
 * never reach the client and collapses to the generic validation.invalid_value key.
 */
final readonly class OrderNumber implements StringValueObject
{
    private function __construct(public string $value)
    {
    }

    public static function fromStringValue(string $value): static
    {
        if (! str_starts_with($value, 'ORD-')) {
            throw new RuntimeException("Internal detail that must stay private: {$value}");
        }

        return new self($value);
    }

    public function toStringValue(): string
    {
        return $this->value;
    }
}
