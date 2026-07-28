<?php declare(strict_types=1);

namespace Tests\Mocks\ValueObjects;

use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\StringValueObject;

/**
 * Implements both interfaces, which the parser must reject: there is no way to tell
 * whether the backing primitive is a string or an int.
 */
final readonly class AmbiguousValueObject implements StringValueObject, IntValueObject
{
    private function __construct(public string $value)
    {
    }

    public static function fromStringValue(string $value): static
    {
        return new self($value);
    }

    public static function fromIntValue(int $value): static
    {
        return new self((string)$value);
    }

    public function toStringValue(): string
    {
        return $this->value;
    }

    public function toIntValue(): int
    {
        return (int)$this->value;
    }
}
