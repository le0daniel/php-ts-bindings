<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\ValueObjects;

/**
 * Marks a class as a value object backed by a single string.
 *
 * The class is serialized to, and hydrated from, a plain JSON string. Implementing this
 * interface is the opt-in: no #[Castable] attribute is needed and the type is usable for
 * both input and output.
 *
 * The methods carry the ...Value suffix on purpose. A value object is very likely to already
 * implement Stringable or a domain specific toString(), and this interface must be safe to
 * add to such a class without forcing a rename.
 *
 * fromStringValue() may throw to reject a value. The parser catches any Throwable and reports
 * it as a validation issue on the field, with the original exception attached.
 */
interface StringValueObject
{
    public static function fromStringValue(string $value): static;

    public function toStringValue(): string;
}
