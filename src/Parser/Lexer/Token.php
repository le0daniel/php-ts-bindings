<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Lexer;

use Override;
use Stringable;

/**
 * A single lexeme. `$value` is the RAW source text: string literals keep their quotes and
 * escape sequences, numbers keep their sign and `_` separators, identifiers keep their
 * leading `\`. `$offset` is a byte offset into the input the token was lexed from.
 */
final readonly class Token implements Stringable
{
    public function __construct(
        public TokenType $type,
        public string $value,
        public int $offset,
    ) {
    }

    public function endOffset(): int
    {
        return $this->offset + strlen($this->value);
    }

    public function is(TokenType $type, ?string $value = null): bool
    {
        if ($this->type !== $type) {
            return false;
        }

        return is_null($value) || $this->value === $value;
    }

    public function isAnyTypeOf(TokenType ...$types): bool
    {
        return in_array($this->type, $types, true);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
