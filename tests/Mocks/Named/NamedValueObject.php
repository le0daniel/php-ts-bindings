<?php

declare(strict_types=1);

namespace Tests\Mocks\Named;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\StringValueObject;

/**
 * Brand and Named combined: exported once as `export type AccountId = (string & Brand<"accountId">)`
 * and referenced by name at every use site. A value object's shape never differs per direction, so
 * the one alias covers both.
 */
#[Brand('accountId')]
#[Named('AccountId')]
final readonly class NamedValueObject implements StringValueObject
{
    private function __construct(public string $value)
    {
    }

    public static function fromStringValue(string $value): static
    {
        return new self($value);
    }

    public function toStringValue(): string
    {
        return $this->value;
    }
}
