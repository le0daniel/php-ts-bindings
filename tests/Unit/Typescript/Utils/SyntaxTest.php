<?php declare(strict_types=1);

namespace Tests\Unit\Typescript\Utils;

use Le0daniel\PhpTsBindings\CodeGen\Exceptions\CodeGenException;
use Le0daniel\PhpTsBindings\Typescript\Utils\Syntax;

test('object key', function () {
    expect(Syntax::objectKey('foo'))->toBe('foo');
    expect(Syntax::objectKey('foo', true))->toBe('foo?');
    expect(Syntax::objectKey('foo a'))->toBe('"foo a"');
    expect(Syntax::objectKey('foo a', true))->toBe('"foo a"?');
});

// objectKey() used to carry its own identifier regex that rejected `$`, while isValidIdentifier()
// accepted it. One question must have one answer.
test('object key agrees with isValidIdentifier on every input', function (string $key) {
    expect(Syntax::objectKey($key) === $key)->toBe(Syntax::isValidIdentifier($key));
})->with(['foo', '$foo', 'foo$bar', '_foo', 'Foo1', 'foo a', '1foo', '', 'foo-bar']);

test('module specifier is single quoted', function () {
    expect(Syntax::moduleSpecifier('./types'))->toBe("'./types'");
    expect(Syntax::moduleSpecifier('@scope/pkg'))->toBe("'@scope/pkg'");
});

// TypescriptImport rejects these before ever reaching here, but Syntax is public and must not
// emit a specifier that silently names a module that does not exist.
test('module specifier rejects anything that would not survive the string literal', function (string $specifier) {
    expect(fn() => Syntax::moduleSpecifier($specifier))
        ->toThrow(CodeGenException::class, 'cannot be written as a TypeScript module specifier');
})->with([
    'empty' => [''],
    'single quote' => ["./ty'pes"],
    'double quote' => ['./ty"pes'],
    'backslash' => ['.\\types'],
    'space' => ['./my types'],
    'newline' => ["./types\n"],
    'tab' => ["./ty\tpes"],
]);
