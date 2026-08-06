<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects;

use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;
use Le0daniel\PhpTsBindings\Executor\Exceptions\ValidationException;

/**
 * The int backed counterpart to ValidatedEmail, rejecting with a single message.
 */
final readonly class ValidatedAge implements IntValueObject
{
    public const int MINIMUM = 18;

    private function __construct(public int $value)
    {
    }

    public static function fromIntValue(int $value): static
    {
        if ($value < self::MINIMUM) {
            throw new ValidationException('Must be 18 or older', ['min' => self::MINIMUM]);
        }

        return new self($value);
    }

    public function toIntValue(): int
    {
        return $this->value;
    }
}
