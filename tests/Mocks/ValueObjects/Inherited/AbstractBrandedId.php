<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;

/**
 * The abstract parent variant: never parseable itself, so the attributes can only ever mean
 * "apply to my children".
 */
#[Brand]
#[Named]
abstract readonly class AbstractBrandedId implements IntValueObject
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
