<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\StringValueObject;
use Le0daniel\PhpTsBindings\Executor\Exceptions\ValidationException;

/**
 * Rejects with ValidationException so the exact message reaches the 422 fields verbatim. The
 * Brand attribute is codegen-only metadata and must have zero effect on the runtime envelope.
 */
#[Brand]
final readonly class Sku implements StringValueObject
{
    private function __construct(public string $value)
    {
    }

    public static function fromStringValue(string $value): static
    {
        if (preg_match('/^[A-Z]{3}-\d{3}$/', $value) !== 1) {
            throw new ValidationException('Sku must match ABC-123', ['value' => $value]);
        }

        return new self($value);
    }

    public function toStringValue(): string
    {
        return $this->value;
    }
}
