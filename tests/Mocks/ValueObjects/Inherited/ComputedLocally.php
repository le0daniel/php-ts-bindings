<?php declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;

/**
 * A naming closure works on any class, not only on inherited declarations.
 */
#[Brand(name: Naming::prefixedBrand(...))]
#[Named(name: Naming::suffixedAlias(...))]
final readonly class ComputedLocally implements IntValueObject
{
    private function __construct(public int $value)
    {
    }

    public static function fromIntValue(int $value): static
    {
        return new self($value);
    }

    public function toIntValue(): int
    {
        return $this->value;
    }
}
