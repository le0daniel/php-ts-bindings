<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\ValueObjects;

/**
 * Marks a class as a value object backed by a single int.
 *
 * The class is serialized to, and hydrated from, a plain JSON number. Implementing this
 * interface is the opt-in: no #[Castable] attribute is needed and the type is usable for
 * both input and output.
 *
 * The methods carry the ...Value suffix on purpose, so the interface stays safe to add to a
 * class that already declares a toInt() of its own.
 *
 * fromIntValue() may throw to reject a value. The parser catches any Throwable and reports
 * it as a validation issue on the field, with the original exception attached.
 */
interface IntValueObject
{
    public static function fromIntValue(int $value): static;

    public function toIntValue(): int;
}
