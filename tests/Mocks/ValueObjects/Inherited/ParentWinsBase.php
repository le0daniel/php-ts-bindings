<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;

/**
 * Paired with ParentWinsContract: both carry #[Named], each deriving a differently suffixed alias,
 * so a test can tell which candidate the resolver picked.
 */
#[Named(name: Naming::parentAlias(...))]
abstract readonly class ParentWinsBase implements IntValueObject
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
