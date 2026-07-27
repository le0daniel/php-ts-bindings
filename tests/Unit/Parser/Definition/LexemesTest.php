<?php

namespace Tests\Unit\Parser\Definition;

use Le0daniel\PhpTsBindings\Parser\Definition\Lexemes;

test('single quoted literals resolve only backslash and quote escapes', function () {
    expect(Lexemes::decodeString("'hello'"))->toBe('hello')
        ->and(Lexemes::decodeString("''"))->toBe('')
        ->and(Lexemes::decodeString("'18'"))->toBe('18')
        ->and(Lexemes::decodeString("'   '"))->toBe('   ')
        ->and(Lexemes::decodeString("'key something else'"))->toBe('key something else')
        ->and(Lexemes::decodeString("'it\\'s'"))->toBe("it's")
        ->and(Lexemes::decodeString("'say \"hi\"'"))->toBe('say "hi"')
        ->and(Lexemes::decodeString("'\\\\'"))->toBe('\\')
        ->and(Lexemes::decodeString("'foo\\\\bar'"))->toBe('foo\\bar')
        // PHP does NOT interpret \n inside single quotes.
        ->and(Lexemes::decodeString("'a\\nb'"))->toBe('a\\nb');
});

test('double quoted literals resolve the full escape set', function () {
    expect(Lexemes::decodeString('"hello"'))->toBe('hello')
        ->and(Lexemes::decodeString('""'))->toBe('')
        ->and(Lexemes::decodeString('"0"'))->toBe('0')
        ->and(Lexemes::decodeString('"it\'s"'))->toBe("it's")
        ->and(Lexemes::decodeString('"say \\"hi\\""'))->toBe('say "hi"')
        ->and(Lexemes::decodeString('"a\\nb"'))->toBe("a\nb")
        ->and(Lexemes::decodeString('"a\\tb"'))->toBe("a\tb")
        ->and(Lexemes::decodeString('"a\\rb"'))->toBe("a\rb")
        ->and(Lexemes::decodeString('"\\x41"'))->toBe('A')
        ->and(Lexemes::decodeString('"\\101"'))->toBe('A')
        ->and(Lexemes::decodeString('"\\u{1F600}"'))->toBe("\u{1F600}")
        ->and(Lexemes::decodeString('"C:\\\\path"'))->toBe('C:\\path')
        ->and(Lexemes::decodeString('"non-empty-string"'))->toBe('non-empty-string');
});

test('integers decode with separators, sign and radix prefixes', function () {
    expect(Lexemes::decodeInt('0'))->toBe(0)
        ->and(Lexemes::decodeInt('1'))->toBe(1)
        ->and(Lexemes::decodeInt('-1'))->toBe(-1)
        ->and(Lexemes::decodeInt('+1'))->toBe(1)
        ->and(Lexemes::decodeInt('-100'))->toBe(-100)
        ->and(Lexemes::decodeInt('1_000'))->toBe(1000)
        ->and(Lexemes::decodeInt('-1_000_000'))->toBe(-1000000)
        ->and(Lexemes::decodeInt('0x1F'))->toBe(31)
        ->and(Lexemes::decodeInt('0X1f'))->toBe(31)
        ->and(Lexemes::decodeInt('0b1010'))->toBe(10)
        ->and(Lexemes::decodeInt('0o17'))->toBe(15)
        ->and(Lexemes::decodeInt('-0x10'))->toBe(-16)
        // A leading zero stays DECIMAL, matching the old tokenizer's (int) cast.
        // This is why intval($value, 0) cannot be used here.
        ->and(Lexemes::decodeInt('010'))->toBe(10);
});

test('floats decode with separators and exponents', function () {
    expect(Lexemes::decodeFloat('0.1'))->toBe(0.1)
        ->and(Lexemes::decodeFloat('-0.3'))->toBe(-0.3)
        ->and(Lexemes::decodeFloat('.5'))->toBe(0.5)
        ->and(Lexemes::decodeFloat('1e5'))->toBe(100000.0)
        ->and(Lexemes::decodeFloat('1.5e-3'))->toBe(0.0015)
        ->and(Lexemes::decodeFloat('1_000.5'))->toBe(1000.5);
});
