<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BoolNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\IntNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\StringNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

/**
 * A PHP array is a hash map that folds a canonical decimal integer string into an int the moment
 * it becomes a key. The executor never undoes or redoes that: it hands the key to the key node as
 * it arrives, which is already the form `$record[$key]` will store it under.
 *
 * These pin the consequences at both ends - which keys a record accepts, and which key *types* can
 * describe a key PHP is able to hold at all.
 */
test('the key is validated as PHP already folded it', function (string $type, array $input, array $expected) {
    $result = executeParse($type, $input);

    expect($result)->toBeSuccess()
        ->and($result->value)->toBe($expected);
})->with([
    // json_decode already handed these over as ints, and IntNode is what declared them.
    'int keys arrive folded' => ['array<int, string>', ['1' => 'a', '2' => 'b'], [1 => 'a', 2 => 'b']],
    'negative int key' => ['array<int, string>', ['-1' => 'a'], [-1 => 'a']],
    'int literal key' => ['array<1|2, string>', ['1' => 'x'], [1 => 'x']],
    'refined int key' => ['array<positive-int, string>', ['1' => 'x'], [1 => 'x']],

    // These are not canonical integers, so PHP keeps them as strings and a string key node takes
    // them unchanged. Coercing with filter_var would have folded the first three into `1` and lost
    // them.
    'leading space is a string key' => ['array<string, int>', [' 1' => 1], [' 1' => 1]],
    'leading zero is a string key' => ['array<string, int>', ['01' => 1], ['01' => 1]],
    'leading plus is a string key' => ['array<string, int>', ['+1' => 1], ['+1' => 1]],
    'minus zero is a string key' => ['array<string, int>', ['-0' => 1], ['-0' => 1]],
    'wider than an int is a string key' => ['array<string, int>', ['9223372036854775808' => 1], ['9223372036854775808' => 1]],
    'ordinary string key' => ['array<string, int>', ['abc' => 1], ['abc' => 1]],
]);

test('a numeric key is rejected by a string keyed record instead of silently becoming an int key', function () {
    // The point of validating the key as it stands. PHP has no string key '1' to give, so
    // accepting this would answer an array<int, string> under a signature promising string keys.
    $result = executeParse('array<string, string>', ['1' => 'a']);

    expect($result)->toBeFailureAt('1', 'validation.invalid_key_type');
});

test('a string keyed record still accepts a key that merely looks numeric', function () {
    // Only a canonical integer folds, so these stay describable by array<string, V>.
    expect(executeParse('array<string, string>', ['01' => 'a', '1.5' => 'b', '' => 'c']))->toBeSuccess();
});

test('a non numeric key is rejected by an int keyed record', function () {
    expect(executeParse('array<int, string>', ['abc' => 'a']))->toBeFailureAt('abc', 'validation.invalid_key_type');
});

/**
 * The same folding rule read from the other side: a key *type* that describes only keys PHP folds
 * away can never match anything, so it is rejected when the schema is parsed rather than matching
 * nothing at runtime.
 */
test('a string literal key that PHP would fold is not a usable key type', function (string $type) {
    expect(fn () => new TypeParser()->parse($type))
        ->toThrow(InvalidSyntaxException::class, "Array key type must be 'string', 'int' or a union of string/int literals");
})->with([
    'single digit' => ["array<'1', string>"],
    'negative' => ["array<'-1', string>"],
    'zero' => ["array<'0', string>"],
    'inside a union' => ["array<'one'|'2', string>"],
]);

test('a string literal key PHP cannot fold is usable', function (string $type) {
    expect(new TypeParser()->parse($type))->toBeInstanceOf(RecordNode::class);
})->with([
    'leading zero' => ["array<'01', string>"],
    'leading plus' => ["array<'+1', string>"],
    'minus zero' => ["array<'-0', string>"],
    'not a number' => ["array<'one', string>"],
    'decimal' => ["array<'1.5', string>"],
]);

test('an int literal key is usable and emits as the string a JSON key would carry', function () {
    expect(typescriptFor(new TypeParser()->parse('array<1|2, string>'), \Le0daniel\PhpTsBindings\Data\IO::OUTPUT)->type)
        ->toBe('Partial<Record<"1"|"2",string>>');
});

test('a hand built record rejects a key the executor could not honour', function () {
    // The parser catches this with a syntax error pointing at the token. AstValidator is what
    // catches an AST that never went through the parser.
    expect(fn () => new RecordNode(new BoolNode(), new StringNode())->validate())
        ->toThrow(ParserException::class, "A record key must be 'string', 'int' or a union of string/int literals");
});

test('a hand built record with a usable key validates', function () {
    validateAst(new RecordNode(new IntNode(), new StringNode()));
    validateAst(new RecordNode(new StringNode(), new StringNode()));
});
