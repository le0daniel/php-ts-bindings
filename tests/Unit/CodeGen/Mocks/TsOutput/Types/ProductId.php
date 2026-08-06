<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks\TsOutput\Types;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;

/**
 * A brand without a name: the tag is lcfirst() of the class, and the type is rendered inline at
 * every use site rather than declared as an alias.
 */
#[Brand]
final readonly class ProductId implements IntValueObject
{
    private function __construct(public int $value)
    {
    }

    public static function fromIntValue(int $value): static
    {
        if ($value < 1) {
            throw new InvalidArgumentException("ProductId must be positive, got {$value}");
        }

        return new self($value);
    }

    public function toIntValue(): int
    {
        return $this->value;
    }
}
