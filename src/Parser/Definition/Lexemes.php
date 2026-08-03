<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Definition;

use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;

/**
 * Decodes raw lexemes produced by the Lexer into PHP values.
 *
 * The Lexer deliberately performs no interpretation: a STRING token keeps its quotes and
 * escape sequences, and an INT token keeps its sign, `_` separators and radix prefix. This
 * class is where that raw text becomes a value, and it is the only place in the parser
 * allowed to make that decision.
 */
final readonly class Lexemes
{
    private const array ESCAPE_SEQUENCES = [
        '\\' => '\\',
        'n' => "\n",
        'r' => "\r",
        't' => "\t",
        'f' => "\f",
        'v' => "\v",
        'e' => "\x1B",
    ];

    /**
     * Strips the surrounding quotes and resolves escape sequences, following PHP's own
     * string semantics: single quoted strings recognise only `\\` and `\'`, double quoted
     * strings recognise the full set.
     *
     * @param string $lexeme The raw STRING lexeme, quotes included.
     */
    public static function decodeString(string $lexeme): string
    {
        $quote = $lexeme[0] ?? '';
        $inner = substr($lexeme, 1, -1);

        if ($quote === "'") {
            return str_replace(['\\\\', "\\'"], ['\\', "'"], $inner);
        }

        return self::resolveEscapeSequences(str_replace('\\"', '"', $inner));
    }

    /**
     * Handles `_` separators, an explicit sign and the 0x / 0b / 0o radix prefixes.
     *
     * Dispatching on the prefix is deliberate: `intval($value, 0)` returns 0 for `0o17` and
     * reads a leading-zero decimal such as `010` as octal, neither of which is wanted here.
     *
     * @param string $lexeme The raw INT lexeme.
     */
    public static function decodeInt(string $lexeme): int
    {
        $value = str_replace('_', '', $lexeme);
        $isNegative = str_starts_with($value, '-');

        if ($isNegative || str_starts_with($value, '+')) {
            $value = substr($value, 1);
        }

        $magnitude = match (strtolower(substr($value, 0, 2))) {
            '0x' => (int)hexdec(substr($value, 2)),
            '0b' => (int)bindec(substr($value, 2)),
            '0o' => (int)octdec(substr($value, 2)),
            default => (int)$value,
        };

        return $isNegative ? -$magnitude : $magnitude;
    }

    /**
     * Handles `_` separators and exponents.
     *
     * @param string $lexeme The raw FLOAT lexeme.
     */
    public static function decodeFloat(string $lexeme): float
    {
        return (float)str_replace('_', '', $lexeme);
    }

    /**
     * Implementation based on PHPStan's StringUnescaper, which in turn is based on
     * nikic/PHP-Parser. Ported rather than imported: phpstan/phpdoc-parser is only a
     * transitive development dependency of this package.
     */
    private static function resolveEscapeSequences(string $string): string
    {
        $resolved = preg_replace_callback(
            '~\\\\([\\\\nrtfve]|[xX][0-9a-fA-F]{1,2}|[0-7]{1,3}|u\{([0-9a-fA-F]+)\})~',
            static function (array $matches): string {
                $sequence = $matches[1];

                if (isset(self::ESCAPE_SEQUENCES[$sequence])) {
                    return self::ESCAPE_SEQUENCES[$sequence];
                }

                if ($sequence[0] === 'x' || $sequence[0] === 'X') {
                    return chr(self::toByte((int)hexdec(substr($sequence, 1))));
                }

                if ($sequence[0] === 'u') {
                    return self::codePointToUtf8((int)hexdec($matches[2] ?? ''));
                }

                // Three octal digits reach 511, which PHP itself truncates to a byte.
                return chr(self::toByte((int)octdec($sequence)));
            },
            $string,
        );

        if ($resolved === null) {
            throw new ParserException('Failed to resolve escape sequences: ' . preg_last_error_msg());
        }

        return $resolved;
    }

    /**
     * @return int<0, 255>
     */
    private static function toByte(int $value): int
    {
        /** @var int<0, 255> $byte */
        $byte = $value & 0xFF;
        return $byte;
    }

    private static function codePointToUtf8(int $codePoint): string
    {
        if ($codePoint <= 0x7F) {
            return chr(self::toByte($codePoint));
        }

        if ($codePoint <= 0x7FF) {
            return chr(($codePoint >> 6) + 0xC0)
                . chr(($codePoint & 0x3F) + 0x80);
        }

        if ($codePoint <= 0xFFFF) {
            return chr(($codePoint >> 12) + 0xE0)
                . chr((($codePoint >> 6) & 0x3F) + 0x80)
                . chr(($codePoint & 0x3F) + 0x80);
        }

        if ($codePoint <= 0x1FFFFF) {
            return chr(($codePoint >> 18) + 0xF0)
                . chr((($codePoint >> 12) & 0x3F) + 0x80)
                . chr((($codePoint >> 6) & 0x3F) + 0x80)
                . chr(($codePoint & 0x3F) + 0x80);
        }

        // Invalid UTF-8 code point escape sequence: code point too large.
        return "\xef\xbf\xbd";
    }
}
