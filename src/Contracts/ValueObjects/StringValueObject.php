<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\ValueObjects;

use Le0daniel\PhpTsBindings\Executor\Exceptions\ValidationException;

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
 *
 * Throw a ValidationException to choose what the client is told - one issue per message it carries.
 * Any other Throwable has no message fit for a client, so it collapses to the generic
 * `validation.invalid_value` key and keeps its message in the debug info.
 */
interface StringValueObject
{
    public static function fromStringValue(string $value): static;

    /**
     * @throws ValidationException
     */
    public function toStringValue(): string;
}
