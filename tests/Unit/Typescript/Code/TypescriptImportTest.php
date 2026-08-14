<?php

declare(strict_types=1);

use Le0daniel\PhpTsBindings\CodeGen\Exceptions\CodeGenException;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptImport;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\InvalidStringLiteralException;

test('imports nothing by default', function () {
    $import = new TypescriptImport('./lib/types');

    expect($import->from)->toBe('./lib/types')
        ->and($import->values)->toBe([])
        ->and($import->types)->toBe([])
        ->and($import->isEmpty())->toBeTrue();
});

test('keeps value imports and type imports in separate buckets', function () {
    $import = new TypescriptImport('./lib/utils', values: ['queryKey'], types: ['QueryKey']);

    expect($import->values)->toBe(['queryKey'])
        ->and($import->types)->toBe(['QueryKey'])
        ->and($import->isEmpty())->toBeFalse();
});

test('takes a single name as a plain string', function () {
    expect(TypescriptImport::values('./lib/utils', 'queryKey')->values)->toBe(['queryKey'])
        ->and(TypescriptImport::types('./lib/types', 'Brand')->types)->toBe(['Brand']);
});

test('takes a list of names', function () {
    expect(TypescriptImport::types('./lib/types', ['Brand', 'Order'])->types)->toBe(['Brand', 'Order'])
        ->and(TypescriptImport::values('./lib/utils', ['queryKey', 'commandKey'])->values)
        ->toBe(['commandKey', 'queryKey']);
});

test('the named constructors leave the other bucket empty', function () {
    expect(TypescriptImport::types('./lib/types', 'Brand')->values)->toBe([])
        ->and(TypescriptImport::values('./lib/utils', 'queryKey')->types)->toBe([]);
});

test('sorts names alphabetically', function () {
    $import = new TypescriptImport(
        './lib/types',
        values: ['zeta', 'alpha', 'Mike'],
        types: ['Zulu', 'Alpha', 'Kilo'],
    );

    // strcmp is byte order, so uppercase sorts before lowercase.
    expect($import->values)->toBe(['Mike', 'alpha', 'zeta'])
        ->and($import->types)->toBe(['Alpha', 'Kilo', 'Zulu']);
});

test('drops duplicate names inside a bucket', function () {
    $import = new TypescriptImport(
        './lib/types',
        values: ['queryKey', 'queryKey'],
        types: ['Brand', 'Brand', 'Order'],
    );

    expect($import->values)->toBe(['queryKey'])
        ->and($import->types)->toBe(['Brand', 'Order']);
});

test('drops a type import that is also imported as a value', function () {
    // `import type {Foo}` next to `import {Foo}` from one module is TS2440. The value import
    // already carries the type meaning, so the value wins.
    $import = new TypescriptImport('./lib/types', values: ['Status'], types: ['Status', 'Order']);

    expect($import->values)->toBe(['Status'])
        ->and($import->types)->toBe(['Order']);
});

test('reports whether it imports anything', function () {
    expect(new TypescriptImport('./x')->isEmpty())->toBeTrue()
        ->and(new TypescriptImport('./x', values: [])->isEmpty())->toBeTrue()
        ->and(TypescriptImport::values('./x', 'a')->isEmpty())->toBeFalse()
        ->and(TypescriptImport::types('./x', 'A')->isEmpty())->toBeFalse();
});

test('rejects an empty module specifier', function () {
    expect(fn () => new TypescriptImport(''))
        ->toThrow(CodeGenException::class, 'cannot be written as a TypeScript module specifier');
});

test('rejects a module specifier that could not be written as a string literal', function (string $from) {
    expect(fn () => TypescriptImport::values($from, 'a'))
        ->toThrow(CodeGenException::class, 'cannot be written as a TypeScript module specifier');
})->with([
    'single quote' => ["./li'b"],
    'double quote' => ['./li"b'],
    'backslash' => ['.\\lib'],
    'space' => ['./li b'],
    'newline' => ["./lib\n"],
    'leading whitespace' => [' ./lib'],
]);

test('rejects a name that is not a valid TypeScript identifier', function (string $name) {
    expect(fn () => TypescriptImport::values('./lib/types', $name))
        ->toThrow(InvalidStringLiteralException::class, 'is not a valid TypeScript identifier')
        ->and(fn () => TypescriptImport::types('./lib/types', $name))
        ->toThrow(InvalidStringLiteralException::class, 'is not a valid TypeScript identifier');
})->with([
    'empty' => [''],
    'padded' => [' Foo '],
    'kebab case' => ['foo-bar'],
    'leading digit' => ['1Foo'],
    'a space' => ['Foo Bar'],
    'a dotted path' => ['Foo.Bar'],
    // The old `"type Foo"` prefix convention is not an input format any more.
    'the old type prefix' => ['type Foo'],
    'an alias' => ['Foo as Bar'],
    'a namespace import' => ['* as ns'],
]);

test('accepts identifiers with dollar signs and underscores', function () {
    $import = TypescriptImport::values('./lib/utils', ['$foo', '_bar', 'Foo$1', '_']);

    expect($import->values)->toBe(['$foo', 'Foo$1', '_', '_bar']);
});

test('names the module in the error message so the bad import can be found', function () {
    expect(fn () => TypescriptImport::types('./lib/types', 'foo-bar'))
        ->toThrow(InvalidStringLiteralException::class, "imported from './lib/types'");
});

test('merges the buckets of two imports of the same module', function () {
    $merged = TypescriptImport::types('./lib/types', ['Order'])
        ->merge(new TypescriptImport('./lib/types', values: ['queryKey'], types: ['Brand']));

    expect($merged->from)->toBe('./lib/types')
        ->and($merged->types)->toBe(['Brand', 'Order'])
        ->and($merged->values)->toBe(['queryKey']);
});

test('dedupes while merging', function () {
    $merged = TypescriptImport::types('./lib/types', ['Brand', 'Order'])
        ->merge(TypescriptImport::types('./lib/types', ['Brand', 'Customer']));

    expect($merged->types)->toBe(['Brand', 'Customer', 'Order']);
});

test('a merged value import removes the same name from the type bucket', function () {
    $merged = TypescriptImport::types('./lib/types', ['Status', 'Order'])
        ->merge(TypescriptImport::values('./lib/types', 'Status'));

    expect($merged->values)->toBe(['Status'])
        ->and($merged->types)->toBe(['Order']);
});

test('refuses to merge imports of different modules', function () {
    expect(fn () => TypescriptImport::types('./lib/types', 'Brand')
        ->merge(TypescriptImport::types('./lib/utils', 'Brand')))
        ->toThrow(CodeGenException::class, 'different modules');
});

test('merging leaves both operands untouched', function () {
    $one = TypescriptImport::types('./lib/types', 'Brand');
    $two = TypescriptImport::values('./lib/types', 'queryKey');

    // Discarding the result is the point of this test, hence the explicit (void).
    (void) $one->merge($two);

    expect($one->types)->toBe(['Brand'])
        ->and($one->values)->toBe([])
        ->and($two->types)->toBe([])
        ->and($two->values)->toBe(['queryKey']);
});

test('merging is order independent', function () {
    $one = new TypescriptImport('./lib/types', values: ['queryKey'], types: ['Order']);
    $two = new TypescriptImport('./lib/types', values: ['commandKey'], types: ['Brand', 'Order']);

    expect($one->merge($two)->values)->toBe($two->merge($one)->values)
        ->and($one->merge($two)->types)->toBe($two->merge($one)->types);
});
