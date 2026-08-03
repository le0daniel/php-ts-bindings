<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Lexer;

use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Parser\Lexer\Exceptions\UnexpectedCharacterException;

/**
 * A purely lexical scanner for PHPStan/Psalm type strings.
 *
 * It performs no interpretation: it does not merge `Foo` `::` `BAR` into a class constant,
 * it does not merge `[` `]` into a list modifier, it does not decide that `true` is a
 * boolean, and it does not unquote string literals. It splits the input into lexemes.
 *
 * The stream is lossless — imploding every token value reproduces the input exactly — and
 * therefore includes WHITESPACE tokens. Consumers are expected to filter those out once.
 */
final class Lexer
{
    private static ?string $pattern = null;

    /** @var array<string, TokenType>|null */
    private static ?array $marks = null;

    /**
     * @return non-empty-list<Token>
     * @throws UnexpectedCharacterException
     */
    public function tokenize(string $input): array
    {
        $matches = [];
        $result = preg_match_all(self::pattern(), $input, $matches, PREG_SET_ORDER);
        if ($result === false) {
            throw new ParserException("Failed to tokenize input: " . preg_last_error_msg());
        }

        $marks = self::marks();
        $tokens = [];
        $offset = 0;

        foreach ($matches as $match) {
            $value = $match[0];
            $tokens[] = new Token($marks[$match['MARK']], $value, $offset);
            $offset += strlen($value);
        }

        // The pattern is anchored, so matching stops at the first character that starts no
        // token. Everything before $offset is lexed; $offset itself is the offending byte.
        if ($offset !== strlen($input)) {
            throw UnexpectedCharacterException::at($input, $offset);
        }

        $tokens[] = new Token(TokenType::EOF, '', $offset);
        return $tokens;
    }

    /**
     * Alternation order follows TokenType's declaration order. `A` anchors every attempt to
     * the end of the previous match; `i` covers identifiers, hex digits and exponents.
     */
    private static function pattern(): string
    {
        if (self::$pattern !== null) {
            return self::$pattern;
        }

        $alternatives = [];
        foreach (TokenType::cases() as $case) {
            $pattern = $case->pattern();
            if ($pattern === null) {
                continue;
            }
            $alternatives[] = "(?:{$pattern})(*MARK:{$case->name})";
        }

        return self::$pattern = '~' . implode('|', $alternatives) . '~Ai';
    }

    /**
     * @return array<string, TokenType>
     */
    private static function marks(): array
    {
        if (self::$marks !== null) {
            return self::$marks;
        }

        /** @var array<string, TokenType> $marks */
        $marks = [];
        foreach (TokenType::cases() as $case) {
            $marks[$case->name] = $case;
        }

        return self::$marks = $marks;
    }
}
