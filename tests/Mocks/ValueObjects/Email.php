<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\StringValueObject;

#[Brand]
final readonly class Email implements StringValueObject
{
    private function __construct(public string $value)
    {
    }

    public static function fromStringValue(string $value): static
    {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email: {$value}");
        }

        return new self($value);
    }

    public function toStringValue(): string
    {
        return $this->value;
    }
}
