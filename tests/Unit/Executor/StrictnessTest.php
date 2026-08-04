<?php declare(strict_types=1);

namespace Tests\Unit\Executor;

use Le0daniel\PhpTsBindings\Executor\Data\ParsingOptions;
use Le0daniel\PhpTsBindings\Executor\Data\SerializationOptions;
use stdClass;

/**
 * The library's rule is that nothing unrepresentable is silently degraded. TypescriptGenerator
 * throws rather than emit a placeholder; these are the executor-side equivalents, each of which
 * used to return a Success carrying a value the caller never produced.
 */

test('coercion never fabricates a value out of a non scalar', function (mixed $value) {
    $result = executeParse('array{name: string}', ['name' => $value], new ParsingOptions(coercePrimitives: true));

    // Before: (string) $value produced the literal "Array", or threw for an object.
    expect($result)->toBeFailureAt('name', 'validation.invalid_type');
})->with([
    'list' => [['a', 'b']],
    'map' => [['a' => 'b']],
    'object' => [new stdClass()],
]);

test('coercion still casts scalars', function (mixed $value, string $expected) {
    $result = executeParse('array{name: string}', ['name' => $value], new ParsingOptions(coercePrimitives: true));

    expect($result)->toBeSuccess()
        ->and($result->value)->toBe(['name' => $expected]);
})->with([
    'int' => [1, '1'],
    'float' => [1.5, '1.5'],
    'true' => [true, '1'],
    'false' => [false, ''],
]);

test('serialization proves the type instead of repairing it', function (string $type, mixed $value) {
    // Output comes out of the application's own code. A near miss is a bug to report, not to fix.
    expect(executeSerialize($type, $value))->toBeFailure();
})->with([
    'numeric string as float' => ['float', '1.5'],
    'padded numeric string as float' => ['float', ' 1e3'],
    'numeric string as int' => ['int', '5'],
]);

test('serialization of a nullable branch fails instead of nulling it when partial failures are off', function () {
    $result = executeSerialize(
        'array{id: int, user: array{name: string}|null}',
        ['id' => 1, 'user' => ['name' => 123]],
        new SerializationOptions(partialFailures: false),
    );

    expect($result)->toBeFailure();
});

test('partial failures remain available for direct executor callers', function () {
    $result = executeSerialize(
        'array{id: int, user: array{name: string}|null}',
        ['id' => 1, 'user' => ['name' => 123]],
        new SerializationOptions(partialFailures: true),
    );

    expect($result)->toBeSuccess()
        ->and(json_encode($result->value))->toBe('{"id":1,"user":null}')
        ->and($result->isPartial())->toBeTrue();
});

test('a union succeeding at the root clears the issues its rejected arms produced', function () {
    // At the root pathAsString() is '__root', which is a prefix of nothing nested, so issues left
    // by a rejected arm used to survive the match and make a clean Success report as partial.
    // (A null value takes UnionHandler's fast path at :72 and never records anything, so the
    // repro has to be a non-null value that a later arm accepts.)
    $result = executeParse('array{a: string}|array{b: int}', ['b' => 1]);

    expect($result)->toBeSuccess()
        ->and($result->issues->isEmpty())->toBeTrue()
        ->and($result->isPartial())->toBeFalse();
});

test('every failure carries at least one issue', function (string $type, mixed $value) {
    $result = executeParse($type, $value);

    expect($result)->toBeFailure()
        ->and($result->issues->allFlat())->not->toBeEmpty();
})->with([
    'list given a map' => ['list<string>', ['a' => 1]],
    'list given a scalar' => ['list<string>', 'nope'],
    'record given a list' => ['array<string, string>', 'nope'],
    'tuple given a scalar' => ['array{string, int}', 'nope'],
    'tuple too short' => ['array{string, int}', ['a']],
    'tuple too long' => ['array{string, int}', ['a', 1, true]],
    'enum case literal' => [\Tests\Mocks\ResultEnum::class . '::SUCCESS', 'NOPE'],
]);

test('every serialization failure carries at least one issue', function (string $type, mixed $value) {
    $result = executeSerialize($type, $value, new SerializationOptions(partialFailures: false));

    expect($result)->toBeFailure()
        ->and($result->issues->allFlat())->not->toBeEmpty();
})->with([
    'list given a scalar' => ['list<string>', 'nope'],
    'record given a scalar' => ['array<string, string>', 'nope'],
    'tuple given a scalar' => ['array{string, int}', 'nope'],
    'enum given a foreign value' => [\Tests\Mocks\ResultEnum::class, 'NOPE'],
]);

test('serializing a tuple shorter than its arity fails without reading past the end', function () {
    // The parse path checked arity; serialize indexed $value[$index] blindly and warned.
    $result = executeSerialize('array{string, int}', ['only-one'], new SerializationOptions(partialFailures: false));

    expect($result)->toBeFailure()
        ->and($result->issues->allFlat())->not->toBeEmpty();
});
