<?php declare(strict_types=1);

namespace Tests\Mocks\ValueObjects;

use Le0daniel\PhpTsBindings\Contracts\ValueObjects\StringValueObject;
use LogicException;

/**
 * Throws on the way out, to pin the serialize side catch.
 */
final readonly class ExplodingValueObject implements StringValueObject
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
        throw new LogicException('boom on serialize');
    }
}
