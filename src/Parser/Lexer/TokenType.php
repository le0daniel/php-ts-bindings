<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Lexer;

/**
 * The declaration order of these cases IS the alternation order of the Lexer's master
 * pattern. Do not reorder without reading the notes on each fragment.
 */
enum TokenType
{
    case WHITESPACE;
    case IDENTIFIER;
    case VARIABLE;
    case STRING;
    case ELLIPSIS;
    case DOUBLE_COLON;
    case DOUBLE_ARROW;
    case ARROW;
    case EQUALS;
    case COLON;
    case PIPE;
    case AMPERSAND;
    case QUESTION_MARK;
    case LPAREN;
    case RPAREN;
    case LT;
    case GT;
    case LBRACE;
    case RBRACE;
    case LBRACKET;
    case RBRACKET;
    case COMMA;
    case ASTERISK;
    case FLOAT;
    case INT;
    case EOF;

    /**
     * The PCRE fragment producing this token, or null if the token is synthetic.
     * Fragments must not contain capturing groups; the Lexer wraps them in (?:...).
     * The master pattern is compiled with the `i` and `A` modifiers.
     */
    public function pattern(): ?string
    {
        return match ($this) {
            // Emitted, never dropped: the token stream is lossless.
            self::WHITESPACE => '\s++',

            // Before FLOAT/INT so `non-empty-string` and `positive-int` win.
            // The outer `++` glues `\Illuminate\Support\Collection` into one token.
            // A hyphen is legal only in non-initial position, so an identifier can never
            // start with `-`. That single constraint disambiguates `int<-1, max>`.
            self::IDENTIFIER => '(?:[\\\\]?+[a-z_\x80-\xFF][0-9a-z_\x80-\xFF-]*+)++',

            self::VARIABLE => '\$[a-z_\x80-\xFF][0-9a-z_\x80-\xFF]*+',

            // Escapes are honoured; the raw lexeme KEEPS its quotes. A literal may not
            // span a newline, so an unterminated quote fails at its own offset instead of
            // eating the rest of the input.
            self::STRING => '\'(?:\\\\[^\r\n]|[^\'\r\n\\\\])*+\'|"(?:\\\\[^\r\n]|[^"\r\n\\\\])*+"',

            self::ELLIPSIS => '\.\.\.',   // before FLOAT
            self::DOUBLE_COLON => '::',   // before COLON
            self::DOUBLE_ARROW => '=>',   // before EQUALS
            self::ARROW => '->',          // before INT, so `->` never reads `-` as a sign
            self::EQUALS => '=',
            self::COLON => ':',
            self::PIPE => '\|',
            self::AMPERSAND => '&',
            self::QUESTION_MARK => '\?',
            self::LPAREN => '\(',
            self::RPAREN => '\)',
            self::LT => '<',
            self::GT => '>',
            self::LBRACE => '\{',
            self::RBRACE => '\}',
            self::LBRACKET => '\[',
            self::RBRACKET => '\]',
            self::COMMA => ',',
            self::ASTERISK => '\*',

            // FLOAT before INT: `0.1` must not lex as INT(0).
            self::FLOAT => '[+-]?(?:(?:[0-9]++(?:_[0-9]++)*\.[0-9]*+(?:_[0-9]++)*(?:e[+-]?[0-9]++(?:_[0-9]++)*)?)|(?:[0-9]*+(?:_[0-9]++)*\.[0-9]++(?:_[0-9]++)*(?:e[+-]?[0-9]++(?:_[0-9]++)*)?)|(?:[0-9]++(?:_[0-9]++)*e[+-]?[0-9]++(?:_[0-9]++)*))',

            self::INT => '[+-]?(?:(?:0b[01]++(?:_[01]++)*)|(?:0o[0-7]++(?:_[0-7]++)*)|(?:0x[0-9a-f]++(?:_[0-9a-f]++)*)|(?:[0-9]++(?:_[0-9]++)*))',

            self::EOF => null,
        };
    }
}
