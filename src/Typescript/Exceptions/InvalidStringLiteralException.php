<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Typescript\Exceptions;

use Le0daniel\PhpTsBindings\CodeGen\Exceptions\CodeGenException;

/**
 * A user supplied string literal (a #[Brand] tag, a #[Named] alias, a BrandedString/BrandedInt
 * tag) that is emitted verbatim into the generated TypeScript but is not a valid identifier
 * there. Every invalid-identifier failure throws this, regardless of which attribute or utility
 * carried the literal.
 */
final class InvalidStringLiteralException extends CodeGenException
{
    public static function notAValidTypescriptIdentifier(string $literal, string $useSite): self
    {
        return new self(
            "'{$literal}' ({$useSite}) is not a valid TypeScript identifier; it is emitted verbatim into the generated types."
        );
    }
}
