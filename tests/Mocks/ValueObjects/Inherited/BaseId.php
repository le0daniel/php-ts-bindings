<?php declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;

/**
 * The concrete parent variant: usable as a value object in its own right, and still a source of
 * metadata for its children. Each derives its own brand, so BaseId and ChildId stay distinct.
 */
#[Brand]
#[Named]
readonly class BaseId implements IntValueObject
{
    protected function __construct(public int $value)
    {
    }

    public static function fromIntValue(int $value): static
    {
        return new static($value);
    }

    public function toIntValue(): int
    {
        return $this->value;
    }
}
