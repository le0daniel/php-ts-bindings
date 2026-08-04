<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks\TsOutput\Types;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\StringValueObject;

/**
 * Branded and named at once, so the generated types file declares the brand as an alias instead of
 * inlining it: export type Sku = (string & Brand<"sku">).
 */
#[Brand]
#[Named]
final readonly class Sku implements StringValueObject
{
    private function __construct(public string $value)
    {
    }

    public static function fromStringValue(string $value): static
    {
        if ($value === '') {
            throw new InvalidArgumentException('A Sku may not be empty.');
        }

        return new self($value);
    }

    public function toStringValue(): string
    {
        return $this->value;
    }
}
