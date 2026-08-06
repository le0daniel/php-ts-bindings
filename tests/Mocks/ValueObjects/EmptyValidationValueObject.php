<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects;

use Le0daniel\PhpTsBindings\Contracts\ValueObjects\StringValueObject;
use Le0daniel\PhpTsBindings\Executor\Exceptions\ValidationException;

/**
 * Throws a ValidationException carrying no messages at all, to pin that the misuse degrades to a
 * plain rejection instead of a Failure with an empty issues map.
 */
final readonly class EmptyValidationValueObject implements StringValueObject
{
    private function __construct(public string $value)
    {
    }

    public static function fromStringValue(string $value): static
    {
        throw new ValidationException([]);
    }

    public function toStringValue(): string
    {
        return $this->value;
    }
}
