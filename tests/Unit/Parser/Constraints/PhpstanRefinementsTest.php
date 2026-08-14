<?php

declare(strict_types=1);

namespace Tests\Unit\Parser\Constraints;

use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;

/**
 * Every constraint in this library backs exactly one PHPStan refinement. These tests drive the
 * refinements through the parser rather than the constraint classes, because the type string is
 * the only way a constraint can enter an AST.
 */
test('non-empty-list rejects the empty list', function () {
    expect(executeParse('non-empty-list<int>', []))->toBeFailure(IssueMessage::INVALID_MIN->value);
    expect(executeParse('non-empty-list<int>', [1]))->toBeSuccess();
});

test('non-empty-array rejects the empty array', function (string $type) {
    expect(executeParse($type, []))->toBeFailure(IssueMessage::INVALID_MIN->value);
})->with([
    'non-empty-array<string, int>',
    'non-empty-array<int, int>',
]);

test('non-empty-array accepts a populated array', function () {
    expect(executeParse('non-empty-array<string, int>', ['a' => 1]))->toBeSuccess();
    expect(executeParse('non-empty-array<int, int>', [1]))->toBeSuccess();
});

/**
 * Constraints prove untrusted input. Output has already been through static analysis, so
 * serialization trusts it — there is deliberately no option to change that.
 */
test('serialization does not run constraints', function () {
    expect(executeSerialize('non-empty-list<int>', []))->toBeSuccess();
    expect(executeSerialize('positive-int', -5))->toBeSuccess();
    expect(executeSerialize('non-empty-string', ''))->toBeSuccess();
});

test('numeric-string', function () {
    expect(executeParse('numeric-string', '42'))->toBeSuccess();
    expect(executeParse('numeric-string', '-1.5e3'))->toBeSuccess();
    expect(executeParse('numeric-string', 'abc'))->toBeFailure(IssueMessage::NOT_NUMERIC_STRING->value);
    expect(executeParse('numeric-string', 42))->toBeFailure();
});

test('lowercase-string', function () {
    expect(executeParse('lowercase-string', 'abc'))->toBeSuccess();
    expect(executeParse('lowercase-string', ''))->toBeSuccess();
    expect(executeParse('lowercase-string', '123-.'))->toBeSuccess();
    expect(executeParse('lowercase-string', 'Abc'))->toBeFailure(IssueMessage::NOT_LOWERCASE_STRING->value);
});

test('uppercase-string', function () {
    expect(executeParse('uppercase-string', 'ABC'))->toBeSuccess();
    expect(executeParse('uppercase-string', ''))->toBeSuccess();
    expect(executeParse('uppercase-string', 'aBC'))->toBeFailure(IssueMessage::NOT_UPPERCASE_STRING->value);
});

test('non-empty-lowercase-string', function () {
    expect(executeParse('non-empty-lowercase-string', 'abc'))->toBeSuccess();
    expect(executeParse('non-empty-lowercase-string', ''))->toBeFailure(IssueMessage::NOT_EMPTY_STRING->value);
    expect(executeParse('non-empty-lowercase-string', 'Abc'))->toBeFailure(IssueMessage::NOT_LOWERCASE_STRING->value);
});

test('non-empty-uppercase-string', function () {
    expect(executeParse('non-empty-uppercase-string', 'ABC'))->toBeSuccess();
    expect(executeParse('non-empty-uppercase-string', ''))->toBeFailure(IssueMessage::NOT_EMPTY_STRING->value);
    expect(executeParse('non-empty-uppercase-string', 'aBC'))->toBeFailure(IssueMessage::NOT_UPPERCASE_STRING->value);
});

/**
 * The old shared Length constraint dispatched on gettype(), so an int range accepted a string of
 * the right length and a list of the right count. Each constraint now owns one PHP type.
 */
test('an int range rejects everything that is not an int', function (mixed $value) {
    expect(executeParse('int<1, 10>', $value))->toBeFailure();
})->with([['5'], [[1, 2, 3]], [5.0], [null], [true]]);

test('a list length rejects everything that is not an array', function (mixed $value) {
    expect(executeParse('non-empty-list<int>', $value))->toBeFailure();
})->with([['ab'], [5], [null]]);
