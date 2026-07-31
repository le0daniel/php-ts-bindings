<?php declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;

/**
 * A naming closure returning something that is not a valid TypeScript identifier.
 */
#[Brand(name: Naming::invalidIdentifier(...))]
final readonly class BadClosureId implements IntValueObject
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
