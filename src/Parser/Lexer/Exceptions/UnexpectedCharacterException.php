<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Lexer\Exceptions;

use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Parser\Lexer\SourceLocation;

final class UnexpectedCharacterException extends ParserException
{
    private function __construct(
        public readonly string $input,
        public readonly SourceLocation $location,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function at(string $input, int $offset): self
    {
        $location = SourceLocation::fromOffset($input, $offset);
        $character = $input[$offset] ?? '';

        // An unterminated literal is reported at its opening quote, which is honest but
        // unhelpful on its own.
        $hint = $character === "'" || $character === '"'
            ? ' Unterminated string literal.'
            : '';

        return new self($input, $location, implode(PHP_EOL, [
            sprintf(
                'Unexpected character %s at line %d, column %d (offset %d).%s',
                var_export($character, true),
                $location->line,
                $location->column,
                $location->offset,
                $hint,
            ),
            $location->highlight($input),
        ]));
    }
}
