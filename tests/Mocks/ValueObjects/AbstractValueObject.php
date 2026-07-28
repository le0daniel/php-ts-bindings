<?php declare(strict_types=1);

namespace Tests\Mocks\ValueObjects;

use Le0daniel\PhpTsBindings\Contracts\ValueObjects\StringValueObject;

/**
 * Not instantiable, so the parser must reject it rather than emit a node whose
 * factory can never be called.
 */
abstract readonly class AbstractValueObject implements StringValueObject
{
    protected function __construct(public string $value)
    {
    }

    public static function fromStringValue(string $value): static
    {
        throw new \LogicException('Not instantiable');
    }

    public function toStringValue(): string
    {
        return $this->value;
    }
}
