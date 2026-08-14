<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;
use Le0daniel\PhpTsBindings\Executor\Exceptions\ValidationException;

/**
 * The int counterpart to Sku: a branded IntValueObject. The Brand attribute is codegen-only
 * metadata, so the wire shape stays a plain JSON number in both directions.
 */
#[Brand('warehouseId')]
final readonly class WarehouseId implements IntValueObject
{
    private function __construct(public int $value)
    {
    }

    public static function fromIntValue(int $value): static
    {
        if ($value < 1) {
            throw new ValidationException('Warehouse id must be positive', ['value' => $value]);
        }

        return new self($value);
    }

    public function toIntValue(): int
    {
        return $this->value;
    }
}
