<?php

namespace Tests\Unit\Parser\Lexer;

use Le0daniel\PhpTsBindings\Parser\Lexer\Exceptions\UnexpectedCharacterException;
use Le0daniel\PhpTsBindings\Parser\Lexer\Lexer;
use Le0daniel\PhpTsBindings\Parser\Lexer\Token;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;

/**
 * @return list<Token>
 */
function lex(string $input): array
{
    return (new Lexer())->tokenize($input);
}

/**
 * Compact "TYPE(lexeme)" rendering with WHITESPACE and EOF removed.
 *
 * @return list<string>
 */
function significant(string $input): array
{
    return array_values(array_map(
        fn (Token $token) => "{$token->type->name}({$token->value})",
        array_filter(
            lex($input),
            fn (Token $token) => ! $token->isAnyTypeOf(TokenType::WHITESPACE, TokenType::EOF),
        ),
    ));
}

/**
 * One type string per line.
 *
 * @return list<string>
 */
function lines(string $block): array
{
    return explode("\n", $block);
}

/**
 * The real strings used across the existing test suite, plus complex PHPStan constructs
 * the old tokenizer cannot express.
 *
 * @return list<string>
 */
function corpus(): array
{
    $singleLine = <<<'TYPES'
    string|int
    string | int
    string | int[]
    string|int[]|object{name: 5}
    string::class
    string|0|array{0: string, 1: string}
    array{name: string,}
    object{name: string,}
    (string|int)|string
    0|1|-1|0.1|-0.3
    ?float
    (?float)|string
    int<0, 100>
    int<min, 100>
    int<-1, max>
    int<-100, -3>
    positive-int|non-negative-int|non-positive-int|negative-int
    non-empty-string|non-falsy-string|truthy-string|scalar|numeric|mixed
    array{a: string, b: int}
    object{a: string, b: int}
    array{string, int}
    array{0:string, 1: int}
    array{id?: string, name: string}
    array<string>
    array<string, int>
    list<(array{name: string}|null)>
    string[]
    (string|int)[]
    int[][]
    array{id:string}&array{reason:string}
    (array{id:string}|array{token:string})&array{reason:string}
    ResultEnumBase::SUCCESS|ResultEnum::OTHER
    Tests\Feature\Mocks\Paginated<array{id:string}>
    \Illuminate\Support\Collection<int, array{id: string}>
    Omit< \Tests\Unit\Executor\Mocks\UserSchema, "age">
    Pick<array{id: string, name: string, age: int}, "name"|"age">
    BrandedInt<'wow'>
    BrandedString<"accountId">
    Named<"AccountId", string>
    Branded<'accountId', Named<"AccountId", string>>
    1|-2|true|false|'string'
    7|'18'|true
    array{"key something else": OtherType, ...}
    array{'key with spaces': int, "another key": string}
    array{...}
    array{foo: int, ...}
    array{}
    array{'': int}
    array{"key"?: string}
    array{'key'?: string, ...}
    array{0: string, 1?: int, ...}
    array{"a\"b": int}
    array{'a\'b': int}
    callable(string, int): void
    callable(int $a, string ...$rest): bool
    Closure(int, ...): void
    int-mask-of<Foo::*>
    key-of<array{a: 1}>
    value-of<Foo\Bar>
    class-string<Foo>
    array<int, array<string, list<Foo|null>>>
    non-empty-array<string, positive-int>
    list{int, string}
    $this
    static|self|parent
    Foo::BAR|Foo::BAZ
    ?Foo::BAR
    (Foo&Bar)|null
    Foo<T = int>
    T of \Foo\Bar
    ($x is int ? string : bool)
    1e5|.5|1_000|0x1F|0b1010
    TYPES;

    return array_merge(lines($singleLine), [
        "array{\n    id: positive-int,\n    status: OrderStatus::READY_TO_ORDER,\n    fileId?: positive-int\n}",
        "array{\n    id: positive-int,\n    status: OrderStatus::REJECTED,\n    reason: string,\n    tips?: string|null\n}",
    ]);
}

/**
 * ---------------------------------------------------------------------------------------
 * A. Corpus sweep
 * ---------------------------------------------------------------------------------------
 */
test('the token stream is lossless for every type string', function () {
    foreach (corpus() as $input) {
        $roundTrip = implode('', array_map(fn (Token $token) => $token->value, lex($input)));
        expect($roundTrip)->toBe($input, "Round trip failed for: {$input}");
    }
});

test('offsets are contiguous and the stream ends on a zero width EOF', function () {
    foreach (corpus() as $input) {
        $tokens = lex($input);
        $expected = 0;
        foreach ($tokens as $token) {
            expect($token->offset)->toBe($expected, "Bad offset in: {$input}");
            $expected = $token->endOffset();
        }

        $last = $tokens[count($tokens) - 1];
        expect($last->type)->toBe(TokenType::EOF, "Missing EOF in: {$input}")
            ->and($last->value)->toBe('')
            ->and($last->offset)->toBe(strlen($input));
    }
});

test('empty input yields only EOF', function () {
    expect(lex(''))->toHaveCount(1)
        ->and(lex('')[0]->type)->toBe(TokenType::EOF)
        ->and(lex('')[0]->value)->toBe('')
        ->and(lex('')[0]->offset)->toBe(0);
});

/**
 * ---------------------------------------------------------------------------------------
 * B. String literals
 * ---------------------------------------------------------------------------------------
 */
test('every string literal lexes to exactly one STRING token with quotes and escapes intact', function () {
    $literals = lines(<<<'STRINGS'
    'hello'
    "hello"
    ''
    ""
    '18'
    "0"
    'it\'s'
    "say \"hi\""
    "it's"
    'say "hi"'
    'key with spaces'
    "key something else"
    'a|b&c<d>[]{}(),:'
    "Foo::BAR"
    'array{a: int}|null'
    '\\'
    "\\"
    'foo\\bar'
    "C:\\path\\to"
    '...'
    "non-empty-string"
    '   '
    STRINGS);

    foreach ($literals as $literal) {
        expect(significant($literal))->toBe(["STRING({$literal})"], "Failed for: {$literal}");
    }
});

test('single and double quoted literals mix freely and sit next to each other', function () {
    expect(significant("'a'|\"b\""))->toBe(["STRING('a')", 'PIPE(|)', 'STRING("b")'])
        ->and(significant("'a''b'"))->toBe(["STRING('a')", "STRING('b')"])
        ->and(significant('"a""b"'))->toBe(['STRING("a")', 'STRING("b")'])
        ->and(significant('Pick<array{id: string}, "name"|\'age\'>'))->toBe([
            'IDENTIFIER(Pick)', 'LT(<)', 'IDENTIFIER(array)', 'LBRACE({)', 'IDENTIFIER(id)',
            'COLON(:)', 'IDENTIFIER(string)', 'RBRACE(})', 'COMMA(,)',
            'STRING("name")', 'PIPE(|)', "STRING('age')", 'GT(>)',
        ]);
});

test('whitespace inside a string literal is part of the literal, not a WHITESPACE token', function () {
    $tokens = lex('array{"key something else": int}');
    $whitespace = array_filter($tokens, fn (Token $token) => $token->type === TokenType::WHITESPACE);

    // The two spaces inside the key belong to the STRING; only the one after `:` is trivia.
    expect($whitespace)->toHaveCount(1)
        ->and(significant('array{"key something else": int}'))->toBe([
            'IDENTIFIER(array)', 'LBRACE({)', 'STRING("key something else")',
            'COLON(:)', 'IDENTIFIER(int)', 'RBRACE(})',
        ]);
});

/**
 * ---------------------------------------------------------------------------------------
 * C. Quoted keys and unsealed shapes
 * ---------------------------------------------------------------------------------------
 */
test('array shape keys may be quoted and may contain spaces', function () {
    expect(significant('array{"key something else": OtherType, ...}'))->toBe([
        'IDENTIFIER(array)', 'LBRACE({)', 'STRING("key something else")', 'COLON(:)',
        'IDENTIFIER(OtherType)', 'COMMA(,)', 'ELLIPSIS(...)', 'RBRACE(})',
    ])
        ->and(significant("array{'key with spaces': int, \"another key\": string}"))->toBe([
            'IDENTIFIER(array)', 'LBRACE({)', "STRING('key with spaces')", 'COLON(:)',
            'IDENTIFIER(int)', 'COMMA(,)', 'STRING("another key")', 'COLON(:)',
            'IDENTIFIER(string)', 'RBRACE(})',
        ])
        ->and(significant('array{"key"?: string}'))->toBe([
            'IDENTIFIER(array)', 'LBRACE({)', 'STRING("key")', 'QUESTION_MARK(?)',
            'COLON(:)', 'IDENTIFIER(string)', 'RBRACE(})',
        ])
        ->and(significant("array{'': int}"))->toBe([
            'IDENTIFIER(array)', 'LBRACE({)', "STRING('')", 'COLON(:)',
            'IDENTIFIER(int)', 'RBRACE(})',
        ]);
});

test('unsealed shapes and variadics lex the ellipsis as one token', function () {
    expect(significant('array{...}'))
        ->toBe(['IDENTIFIER(array)', 'LBRACE({)', 'ELLIPSIS(...)', 'RBRACE(})'])
        ->and(significant('array{foo: int, ...}'))->toBe([
            'IDENTIFIER(array)', 'LBRACE({)', 'IDENTIFIER(foo)', 'COLON(:)',
            'IDENTIFIER(int)', 'COMMA(,)', 'ELLIPSIS(...)', 'RBRACE(})',
        ])
        ->and(significant('array{0: string, 1?: int, ...}'))->toBe([
            'IDENTIFIER(array)', 'LBRACE({)', 'INT(0)', 'COLON(:)', 'IDENTIFIER(string)',
            'COMMA(,)', 'INT(1)', 'QUESTION_MARK(?)', 'COLON(:)', 'IDENTIFIER(int)',
            'COMMA(,)', 'ELLIPSIS(...)', 'RBRACE(})',
        ])
        ->and(significant('Closure(int, ...): void'))->toBe([
            'IDENTIFIER(Closure)', 'LPAREN(()', 'IDENTIFIER(int)', 'COMMA(,)',
            'ELLIPSIS(...)', 'RPAREN())', 'COLON(:)', 'IDENTIFIER(void)',
        ]);
});

test('empty and trailing comma shapes', function () {
    expect(significant('array{}'))->toBe(['IDENTIFIER(array)', 'LBRACE({)', 'RBRACE(})'])
        ->and(significant('array{name: string,}'))->toBe([
            'IDENTIFIER(array)', 'LBRACE({)', 'IDENTIFIER(name)', 'COLON(:)',
            'IDENTIFIER(string)', 'COMMA(,)', 'RBRACE(})',
        ]);
});

/**
 * ---------------------------------------------------------------------------------------
 * D. The magic that is gone
 * ---------------------------------------------------------------------------------------
 */
test('class constants are three tokens, not one CLASS_CONST', function () {
    expect(significant('Foo::BAR'))
        ->toBe(['IDENTIFIER(Foo)', 'DOUBLE_COLON(::)', 'IDENTIFIER(BAR)'])
        ->and(significant('Tests\Mocks\ResultEnum::SUCCESS'))
        ->toBe(['IDENTIFIER(Tests\Mocks\ResultEnum)', 'DOUBLE_COLON(::)', 'IDENTIFIER(SUCCESS)'])
        ->and(significant('string::class'))
        ->toBe(['IDENTIFIER(string)', 'DOUBLE_COLON(::)', 'IDENTIFIER(class)'])
        ->and(significant('Foo::*'))
        ->toBe(['IDENTIFIER(Foo)', 'DOUBLE_COLON(::)', 'ASTERISK(*)'])
        ->and(significant('?Foo::BAR'))
        ->toBe(['QUESTION_MARK(?)', 'IDENTIFIER(Foo)', 'DOUBLE_COLON(::)', 'IDENTIFIER(BAR)']);
});

test('a trailing double colon lexes without a warning or an error', function () {
    // The old tokenizer read $typeString[$offset + 2] unguarded and emitted a PHP warning.
    expect(significant('Foo::'))->toBe(['IDENTIFIER(Foo)', 'DOUBLE_COLON(::)']);
});

test('brackets are two tokens, not one CLOSED_BRACKETS', function () {
    expect(significant('string[]'))
        ->toBe(['IDENTIFIER(string)', 'LBRACKET([)', 'RBRACKET(])'])
        ->and(significant('int[][]'))->toBe([
            'IDENTIFIER(int)', 'LBRACKET([)', 'RBRACKET(])', 'LBRACKET([)', 'RBRACKET(])',
        ])
        // The old tokenizer could not lex this at all: its merge used a 1 char lookahead.
        ->and(significant('string[ ]'))
        ->toBe(['IDENTIFIER(string)', 'LBRACKET([)', 'RBRACKET(])']);
});

test('true, false and null are plain identifiers', function () {
    expect(significant('true|false|null'))->toBe([
        'IDENTIFIER(true)', 'PIPE(|)', 'IDENTIFIER(false)', 'PIPE(|)', 'IDENTIFIER(null)',
    ])
        ->and(significant("1|-2|true|false|'string'"))->toBe([
            'INT(1)', 'PIPE(|)', 'INT(-2)', 'PIPE(|)', 'IDENTIFIER(true)', 'PIPE(|)',
            'IDENTIFIER(false)', 'PIPE(|)', "STRING('string')",
        ]);
});

/**
 * ---------------------------------------------------------------------------------------
 * E. Identifiers and numbers
 * ---------------------------------------------------------------------------------------
 */
test('hyphenated identifiers do not collide with negative numbers', function () {
    expect(significant('non-empty-string'))->toBe(['IDENTIFIER(non-empty-string)'])
        ->and(significant('positive-int'))->toBe(['IDENTIFIER(positive-int)'])
        ->and(significant('int<-1, max>'))->toBe([
            'IDENTIFIER(int)', 'LT(<)', 'INT(-1)', 'COMMA(,)', 'IDENTIFIER(max)', 'GT(>)',
        ])
        ->and(significant('int<-100, -3>'))->toBe([
            'IDENTIFIER(int)', 'LT(<)', 'INT(-100)', 'COMMA(,)', 'INT(-3)', 'GT(>)',
        ])
        ->and(significant('int-mask-of<Foo::*>'))->toBe([
            'IDENTIFIER(int-mask-of)', 'LT(<)', 'IDENTIFIER(Foo)',
            'DOUBLE_COLON(::)', 'ASTERISK(*)', 'GT(>)',
        ]);
});

test('numbers', function () {
    expect(significant('0|1|-1|0.1|-0.3'))->toBe([
        'INT(0)', 'PIPE(|)', 'INT(1)', 'PIPE(|)', 'INT(-1)', 'PIPE(|)',
        'FLOAT(0.1)', 'PIPE(|)', 'FLOAT(-0.3)',
    ])
        ->and(significant('1e5|.5|1_000|0x1F|0b1010'))->toBe([
            'FLOAT(1e5)', 'PIPE(|)', 'FLOAT(.5)', 'PIPE(|)', 'INT(1_000)',
            'PIPE(|)', 'INT(0x1F)', 'PIPE(|)', 'INT(0b1010)',
        ]);
});

test('namespaced identifiers stay a single token', function () {
    expect(significant('\Illuminate\Support\Collection'))
        ->toBe(['IDENTIFIER(\Illuminate\Support\Collection)'])
        ->and(significant('Omit< \Tests\Unit\Executor\Mocks\UserSchema, "age">'))->toBe([
            'IDENTIFIER(Omit)', 'LT(<)', 'IDENTIFIER(\Tests\Unit\Executor\Mocks\UserSchema)',
            'COMMA(,)', 'STRING("age")', 'GT(>)',
        ]);
});

/**
 * ---------------------------------------------------------------------------------------
 * F. Complex constructs beyond today's grammar
 * ---------------------------------------------------------------------------------------
 */
test('callable signatures lex variables, defaults and variadics', function () {
    expect(significant('callable(int $a, string ...$rest): bool'))->toBe([
        'IDENTIFIER(callable)', 'LPAREN(()', 'IDENTIFIER(int)', 'VARIABLE($a)', 'COMMA(,)',
        'IDENTIFIER(string)', 'ELLIPSIS(...)', 'VARIABLE($rest)', 'RPAREN())',
        'COLON(:)', 'IDENTIFIER(bool)',
    ])
        ->and(significant('callable(int $x = 1): string'))->toBe([
            'IDENTIFIER(callable)', 'LPAREN(()', 'IDENTIFIER(int)', 'VARIABLE($x)',
            'EQUALS(=)', 'INT(1)', 'RPAREN())', 'COLON(:)', 'IDENTIFIER(string)',
        ])
        ->and(significant('$this'))->toBe(['VARIABLE($this)']);
});

test('deeply nested generics and utility types', function () {
    expect(significant('array<int, array<string, list<Foo|null>>>'))->toBe([
        'IDENTIFIER(array)', 'LT(<)', 'IDENTIFIER(int)', 'COMMA(,)',
        'IDENTIFIER(array)', 'LT(<)', 'IDENTIFIER(string)', 'COMMA(,)',
        'IDENTIFIER(list)', 'LT(<)', 'IDENTIFIER(Foo)', 'PIPE(|)', 'IDENTIFIER(null)',
        'GT(>)', 'GT(>)', 'GT(>)',
    ])
        ->and(significant('key-of<array{a: 1}>'))->toBe([
            'IDENTIFIER(key-of)', 'LT(<)', 'IDENTIFIER(array)', 'LBRACE({)',
            'IDENTIFIER(a)', 'COLON(:)', 'INT(1)', 'RBRACE(})', 'GT(>)',
        ])
        ->and(significant('Foo<T = int>'))->toBe([
            'IDENTIFIER(Foo)', 'LT(<)', 'IDENTIFIER(T)', 'EQUALS(=)',
            'IDENTIFIER(int)', 'GT(>)',
        ])
        ->and(significant('($x is int ? string : bool)'))->toBe([
            'LPAREN(()', 'VARIABLE($x)', 'IDENTIFIER(is)', 'IDENTIFIER(int)',
            'QUESTION_MARK(?)', 'IDENTIFIER(string)', 'COLON(:)',
            'IDENTIFIER(bool)', 'RPAREN())',
        ]);
});

test('unions, intersections and grouping', function () {
    expect(significant('(array{id:string}|array{token:string})&array{reason:string}'))->toBe([
        'LPAREN(()', 'IDENTIFIER(array)', 'LBRACE({)', 'IDENTIFIER(id)', 'COLON(:)',
        'IDENTIFIER(string)', 'RBRACE(})', 'PIPE(|)', 'IDENTIFIER(array)', 'LBRACE({)',
        'IDENTIFIER(token)', 'COLON(:)', 'IDENTIFIER(string)', 'RBRACE(})', 'RPAREN())',
        'AMPERSAND(&)', 'IDENTIFIER(array)', 'LBRACE({)', 'IDENTIFIER(reason)',
        'COLON(:)', 'IDENTIFIER(string)', 'RBRACE(})',
    ]);
});

/**
 * ---------------------------------------------------------------------------------------
 * G. Whitespace and multi line input
 * ---------------------------------------------------------------------------------------
 */
test('whitespace is emitted as its own token', function () {
    $tokens = lex('string | int');

    expect(array_map(fn (Token $token) => $token->type, $tokens))->toBe([
        TokenType::IDENTIFIER, TokenType::WHITESPACE, TokenType::PIPE,
        TokenType::WHITESPACE, TokenType::IDENTIFIER, TokenType::EOF,
    ])->and($tokens[1]->value)->toBe(' ');
});

test('multi line array shapes lex identically to their single line form', function () {
    $multiLine = "array{\n    id: positive-int,\n    status: OrderStatus::READY_TO_ORDER\n}";
    $singleLine = 'array{id: positive-int, status: OrderStatus::READY_TO_ORDER}';

    expect(significant($multiLine))->toBe(significant($singleLine));
});

/**
 * ---------------------------------------------------------------------------------------
 * H. Errors
 * ---------------------------------------------------------------------------------------
 */
test('illegal input raises UnexpectedCharacterException instead of being swallowed', function () {
    // The old tokenizer lexed "a#b" as IDENTIFIER(a#b) and never complained.
    $illegal = [
        "'abc",             // unterminated single quote
        '"abc',             // unterminated double quote
        "'abc\"",           // mismatched closing quote
        "\"abc'",           // mismatched closing quote
        "array{'a: int}",   // unterminated quote inside a shape
        "'a\nb'",           // a literal may not span a newline
        "'x\\",             // trailing backslash, no closing quote
        'a#b',
        'Foo\\',            // dangling namespace separator
        '-',                // a bare sign is not a number
        '%',
    ];

    foreach ($illegal as $input) {
        $thrown = null;
        try {
            lex($input);
        } catch (UnexpectedCharacterException $exception) {
            $thrown = $exception;
        }

        expect($thrown)->toBeInstanceOf(
            UnexpectedCharacterException::class,
            'Should have been rejected: '.json_encode($input),
        );
    }
});

test('lexing errors report the offending offset, line and column', function () {
    try {
        lex("array{\n  a: %\n}");
        expect(false)->toBeTrue('Expected UnexpectedCharacterException');
    } catch (UnexpectedCharacterException $exception) {
        expect($exception->location->offset)->toBe(12)
            ->and($exception->location->line)->toBe(2)
            ->and($exception->location->column)->toBe(6)
            ->and($exception->getMessage())->toContain('  a: %')
            ->and($exception->getMessage())->toContain('     ^');
    }
});

test('the error offset points at the first byte that cannot start a token', function () {
    try {
        lex('a#b');
        expect(false)->toBeTrue('Expected UnexpectedCharacterException');
    } catch (UnexpectedCharacterException $exception) {
        expect($exception->location->offset)->toBe(1)
            ->and($exception->location->line)->toBe(1)
            ->and($exception->location->column)->toBe(2)
            ->and($exception->input)->toBe('a#b');
    }
});

test('an unterminated literal is reported at its opening quote with a hint', function () {
    try {
        lex("array{'a: int}");
        expect(false)->toBeTrue('Expected UnexpectedCharacterException');
    } catch (UnexpectedCharacterException $exception) {
        expect($exception->location->offset)->toBe(6)
            ->and($exception->getMessage())->toContain('Unterminated string literal.');
    }
});
