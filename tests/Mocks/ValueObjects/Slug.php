<?php declare(strict_types=1);

namespace Tests\Mocks\ValueObjects;

use Le0daniel\PhpTsBindings\Contracts\ValueObjects\StringValueObject;
use Stringable;

/**
 * Carries no #[Brand], so it must stay a plain `string` in TypeScript.
 * It also implements Stringable and declares its own toString(), which is exactly the
 * collision the ...Value suffix on the interface exists to avoid.
 */
final readonly class Slug implements StringValueObject, Stringable
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

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
