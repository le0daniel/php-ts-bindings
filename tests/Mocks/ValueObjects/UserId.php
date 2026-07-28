<?php declare(strict_types=1);

namespace Tests\Mocks\ValueObjects;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;

#[Brand('customerId')]
final readonly class UserId implements IntValueObject
{
    private function __construct(public int $value)
    {
    }

    public static function fromIntValue(int $value): static
    {
        if ($value < 1) {
            throw new InvalidArgumentException("UserId must be positive, got {$value}");
        }

        return new self($value);
    }

    public function toIntValue(): int
    {
        return $this->value;
    }
}
