<?php declare(strict_types=1);

namespace Tests\Unit\Executor\Exceptions;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Exceptions\ValidationException;

test('a single message normalizes to a one element list', function () {
    $exception = new ValidationException('Must be an email');

    expect($exception->messages)->toBe(['Must be an email'])
        ->and($exception->debugInfo)->toBe([]);
});

test('the exception message joins every message, so a log or a stack trace shows all of them', function () {
    $exception = new ValidationException(['Is required', 'Must contain an @']);

    expect($exception->getMessage())->toBe('Is required, Must contain an @');
});

/**
 * A message list is what the field's issues are made of. Zero messages would return Value::INVALID
 * with nothing recorded, which SchemaExecutor turns into a Failure with an empty issues map - a 422
 * whose details.fields is {}. Rejecting a value without saying why is never intended.
 */
test('an empty message list is rejected, because it would produce a failure with no issues', function () {
    expect(fn() => new ValidationException([]))
        ->toThrow(InvalidArgumentException::class);
});

test('a message list with holes is renumbered, so the issues stay a list', function () {
    $exception = new ValidationException([1 => 'second', 5 => 'sixth']);

    expect($exception->messages)->toBe(['second', 'sixth']);
});

test('every message becomes its own issue, carrying the exception for debugging', function () {
    $exception = new ValidationException(['Is required', 'Must contain an @']);
    $issues = $exception->toIssues();

    expect($issues)->toHaveCount(2)
        ->and(array_map(fn(Issue $issue) => $issue->messageOrLocalizationKey, $issues))
        ->toBe(['Is required', 'Must contain an @'])
        ->and($issues[0]->exception)->toBe($exception)
        ->and($issues[1]->exception)->toBe($exception);
});

test('debug info merges with the callers, and the thrower wins on a shared key', function () {
    $exception = new ValidationException('Rejected', ['value' => 'redacted', 'min' => 18]);
    $issue = $exception->toIssues(['node' => 'SomeNode', 'value' => 'the-secret'])[0];

    expect($issue->debugInfo)->toBe([
        'node' => 'SomeNode',
        'value' => 'redacted',
        'min' => 18,
    ]);
});
