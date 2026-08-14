<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;
use Le0daniel\PhpTsBindings\Executor\Exceptions\ValidationException;

/**
 * An int-backed value object: plain number on the wire, validated object in the handler.
 */
final readonly class Quantity implements IntValueObject
{
    private function __construct(public int $value)
    {
    }

    public static function fromIntValue(int $value): static
    {
        if ($value < 1) {
            throw new ValidationException('Quantity must be at least 1', ['value' => $value]);
        }

        return new self($value);
    }

    public function toIntValue(): int
    {
        return $this->value;
    }
}
