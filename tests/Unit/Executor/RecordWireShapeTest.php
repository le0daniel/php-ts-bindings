<?php

declare(strict_types=1);

namespace Tests\Unit\Executor;

use Generator;
use Le0daniel\PhpTsBindings\Executor\Data\SerializationOptions;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

/**
 * The shape a collection takes on the wire, asserted as JSON text.
 *
 * PHP has one data structure where JavaScript has two. A PHP array is an ordered hash map; JSON
 * has `[]` and `{}`, and json_encode picks between them by looking at the keys it happens to find
 * at that moment. `[0 => 'a', 1 => 'b']` encodes as `["a","b"]` and `['x' => 'a']` as
 * `{"x":"a"}` - from the same declared type, on two different requests.
 *
 * So the shape is decided by the declared type and never by the data: `list<T>` and `T[]` are the
 * only things that promise a packed 0..n-1 array, and every `array<...>` is a record that leaves
 * as an object even when it is empty.
 *
 * Everything here asserts the encoded string on purpose. `expect($value)->toEqual((object) [...])`
 * passes just as happily against a plain array, which is exactly the bug this file exists to
 * catch.
 */
function generatorOf(string ...$values): Generator
{
    yield from $values;
}

/**
 * Records. The first two entries are the ones that matter most: packed int keys are what used to
 * degrade into a JSON array, and an empty record is what used to degrade into `[]`.
 */
dataset('record wire shapes', [
    'packed int keys' => ['array<int, string>', [0 => 'a', 1 => 'b', 2 => 'c'], '{"0":"a","1":"b","2":"c"}'],
    'empty int keyed record' => ['array<int, string>', [], '{}'],
    'single int key' => ['array<int, string>', [0 => 'a'], '{"0":"a"}'],
    'sparse int keys' => ['array<int, string>', [3 => 'a', 7 => 'b'], '{"3":"a","7":"b"}'],
    'negative int key' => ['array<int, string>', [-1 => 'a', 0 => 'b'], '{"-1":"a","0":"b"}'],
    'string keys' => ['array<string, int>', ['a' => 1], '{"a":1}'],
    'empty string keyed record' => ['array<string, int>', [], '{}'],
    // PHP folds '0' into the int 0 on the way in; it is still a JSON object key on the way out.
    'numeric string keys' => ['array<string, int>', ['0' => 1, '1' => 2], '{"0":1,"1":2}'],
    'implicit array-key' => ['array<string>', ['a', 'b'], '{"0":"a","1":"b"}'],
    'non empty array' => ['non-empty-array<int, string>', [0 => 'a'], '{"0":"a"}'],
    'literal keys' => ["array<'one'|'two', string>", ['one' => 'a'], '{"one":"a"}'],
    'refined int keys' => ['array<positive-int, string>', [1 => 'a', 2 => 'b'], '{"1":"a","2":"b"}'],
    'refined string keys' => ['array<non-empty-string, int>', ['a' => 1], '{"a":1}'],

    // A record stays an object wherever it sits, and a list nested inside one stays an array.
    'record of lists' => ['array<int, list<string>>', [0 => ['a']], '{"0":["a"]}'],
    'record of records' => ['array<int, array<int, string>>', [0 => [0 => 'a']], '{"0":{"0":"a"}}'],
    'record of structs' => ['array<int, array{id: string}>', [0 => ['id' => 'x']], '{"0":{"id":"x"}}'],
    'record in a struct' => ['array{items: array<int, string>}', ['items' => ['a']], '{"items":{"0":"a"}}'],
    'empty record in a struct' => ['array{items: array<int, string>}', ['items' => []], '{"items":{}}'],
]);

/**
 * Lists, pinning the carve-out from the other side. If one of these ever encodes as an object the
 * split has been applied too widely.
 */
dataset('list wire shapes', [
    'list' => ['list<string>', ['a', 'b'], '["a","b"]'],
    'empty list' => ['list<string>', [], '[]'],
    'shorthand' => ['string[]', ['a', 'b'], '["a","b"]'],
    'grouped shorthand' => ['(string|int)[]', ['a', 1], '["a",1]'],
    'non empty list' => ['non-empty-list<string>', ['a'], '["a"]'],
    // ListHandler repacks, which is the point: a list with holes is still a JSON array.
    'list with holes is repacked' => ['list<string>', [2 => 'a', 5 => 'b'], '["a","b"]'],
    'tuple' => ['array{string, int}', ['a', 1], '["a",1]'],
    'list of records' => ['list<array<int, string>>', [['a']], '[{"0":"a"}]'],
    'list of string keyed records' => ['list<array<string, int>>', [['a' => 1]], '[{"a":1}]'],
    'list of empty records' => ['list<array<string, int>>', [[]], '[{}]'],
]);

test('a record serializes to the expected JSON object', function (string $type, mixed $value, string $expected) {
    expect(serializedJson($type, $value))->toBe($expected);
})->with('record wire shapes');

test('a list serializes to the expected JSON array', function (string $type, mixed $value, string $expected) {
    expect(serializedJson($type, $value))->toBe($expected);
})->with('list wire shapes');

/**
 * The standing guard. The tables above pin exact strings and will need editing whenever a case is
 * added; these two say the thing that must never change, so a collection kind added later cannot
 * quietly opt out of it.
 */
test('every record is a JSON object, whatever its keys happen to be', function (string $type, mixed $value) {
    expect(serializedJson($type, $value))->toStartWith('{');
})->with('record wire shapes');

test('every list is a JSON array', function (string $type, mixed $value) {
    expect(serializedJson($type, $value))->toStartWith('[');
})->with('list wire shapes');

/**
 * A record serializes anything is_iterable, so a lazily produced collection lands in the same
 * object. This one goes through the executor directly rather than serializedJson(): that helper
 * runs the schema twice, once as parsed and once through the optimizer, and a Generator only
 * traverses once. The optimizer parity of this schema is covered by every other case above.
 */
test('a lazily produced collection serializes to a JSON object', function (callable $make) {
    $result = new SchemaExecutor()->serialize(
        new TypeParser()->parse('array<int, string>'),
        $make(),
        new SerializationOptions(partialFailures: false),
    );

    dump($result);
    expect($result)->toBeSuccess()
        ->and(json_encode($result->value, JSON_THROW_ON_ERROR))->toBe('{"0":"a","1":"b"}');
})->with([
    'array' => [fn () => ['a', 'b']],
    'object' => [fn () => (object)['a', 'b']],
]);

/**
 * A packed int keyed array is the case the whole split exists for, so it is asserted on its own
 * rather than only as a row in a table. json_encode of the underlying PHP array is what the client
 * would have received before, and it is the wrong shape.
 */
test('a record whose keys run 0..n never degrades into a JSON array', function () {
    $value = [0 => 'a', 1 => 'b', 2 => 'c'];

    expect(json_encode($value, JSON_THROW_ON_ERROR))->toBe('["a","b","c"]')
        ->and(serializedJson('array<int, string>', $value))->toBe('{"0":"a","1":"b","2":"c"}');
});

test('an empty record never degrades into a JSON array', function () {
    expect(json_encode([], JSON_THROW_ON_ERROR))->toBe('[]')
        ->and(serializedJson('array<string, int>', []))->toBe('{}')
        ->and(serializedJson('array<int, string>', []))->toBe('{}')
        ->and(serializedJson('list<string>', []))->toBe('[]');
});

/**
 * Serialize, encode, decode, parse. The keys have to come back as the type declared them, or the
 * record is lossy in a way `array<int, V>` would notice the moment it indexed one.
 *
 * The parse leg runs through both decode modes because the transport uses both: the Laravel
 * command path decodes associatively, the query path does not and hands back a stdClass.
 */
test('a record round trips through JSON unchanged', function (string $type, array $value, string $expectedJson) {
    $json = serializedJson($type, $value);
    expect($json)->toBe($expectedJson);

    foreach ([true, false] as $associative) {
        $decoded = json_decode($json, $associative, flags: JSON_THROW_ON_ERROR);
        $result = executeParse($type, $decoded);

        expect($result)->toBeSuccess();
        expect($result->value)->toBe($value);
    }
})->with([
    'packed int keys' => ['array<int, string>', [0 => 'a', 1 => 'b'], '{"0":"a","1":"b"}'],
    'sparse int key' => ['array<int, string>', [42 => 'a'], '{"42":"a"}'],
    'string keys' => ['array<string, int>', ['a' => 1], '{"a":1}'],
    'literal keys' => ["array<'one'|'two', string>", ['one' => 'x'], '{"one":"x"}'],
    'refined int keys' => ['array<positive-int, string>', [1 => 'x'], '{"1":"x"}'],
    'nested record' => ['array<int, array<string, int>>', [0 => ['a' => 1]], '{"0":{"a":1}}'],
]);

test('a list round trips through JSON unchanged', function (string $type, array $value, string $expectedJson) {
    $json = serializedJson($type, $value);
    expect($json)->toBe($expectedJson);

    $result = executeParse($type, json_decode($json, true, flags: JSON_THROW_ON_ERROR));

    expect($result)->toBeSuccess();
    expect($result->value)->toBe($value);
})->with([
    'list' => ['list<string>', ['a', 'b'], '["a","b"]'],
    'empty list' => ['list<string>', [], '[]'],
    'list of records' => ['list<array<string, int>>', [['a' => 1]], '[{"a":1}]'],
]);

test('an int keyed record comes back with int keys, not string ones', function () {
    // PHP folds a numeric string key into an int on assignment, which is why array<int, V> can
    // travel as a JSON object and still be indexed by id on the way back.
    $result = executeParse('array<int, string>', json_decode('{"7":"a","42":"b"}', true, flags: JSON_THROW_ON_ERROR));

    expect($result)->toBeSuccess()
        ->and(array_keys($result->value))->toBe([7, 42])
        ->and(array_keys($result->value))->each->toBeInt();
});

test('a string keyed record keeps its keys as strings', function () {
    $result = executeParse('array<string, int>', ['alpha' => 1, 'beta' => 2]);

    expect($result)->toBeSuccess()
        ->and(array_keys($result->value))->toBe(['alpha', 'beta']);
});

/**
 * Keys are validated one at a time on the way in, which is what makes a refined or literal key
 * type worth declaring. Nothing is validated on the way out - serialization never re-checks what
 * the application produced, the same rule constraints already follow.
 */
test('a key the type does not admit is rejected', function (string $type, mixed $value, string $path) {
    expect(executeParse($type, $value))->toBeFailureAt($path, 'validation.invalid_key_type');
})->with([
    'non numeric key for an int keyed record' => ['array<int, string>', ['abc' => 'a'], 'abc'],
    'unknown literal key' => ["array<'one'|'two', string>", ['three' => 'x'], 'three'],
    'empty key for non-empty-string' => ['array<non-empty-string, int>', ['' => 1], ''],
    'zero key for positive-int' => ['array<positive-int, string>', ['0' => 'x'], '0'],
    'unknown int literal key' => ['array<1|2, string>', ['3' => 'x'], '3'],
]);

test('a bad key is reported once, not once per union arm', function () {
    // The key node records an issue per rejected arm on its way to failing. Those describe a value
    // at this path rather than a key, so they are dropped for the one issue that says which of the
    // two actually failed.
    $result = executeParse("array<'one'|'two'|'three', string>", ['four' => 'x']);

    expect($result)->toBeFailure()
        ->and($result->issues->allFlat())->toHaveCount(1)
        ->and($result->issues->serializeToCompleteString())->toBe('At four: validation.invalid_key_type');
});

test('serializing does not validate keys', function () {
    // Output came out of the application's own code, which PHPStan already analysed against the
    // very return type being serialized. Re-checking a key here would pay at runtime for a
    // guarantee static analysis has given.
    expect(serializedJson("array<'one'|'two', string>", ['three' => 'x']))->toBe('{"three":"x"}')
        ->and(serializedJson('array<positive-int, string>', [0 => 'x']))->toBe('{"0":"x"}');
});

test('a record rejects a value that is not a collection at all', function (string $type, mixed $value) {
    expect(executeParse($type, $value))->toBeFailure();
})->with([
    'string' => ['array<int, string>', 'nope'],
    'int' => ['array<string, int>', 7],
    'bool' => ['array<string, int>', true],
    'null' => ['array<string, int>', null],
]);
