<?php declare(strict_types=1);

namespace Tests\Unit\Parser\Constraints;

use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;

/**
 * `non-empty-string` and `non-falsy-string` are different PHPStan types and the difference is
 * exactly the string "0": it is a valid non-empty-string but not a valid non-falsy-string.
 *
 * NonEmptyString used empty(), which is false for "0", so it rejected a value the type it backs
 * explicitly allows.
 */
test('non-empty-string accepts "0"', function () {
    expect(executeParse('non-empty-string', '0'))->toBeSuccess();
});

test('non-falsy-string rejects "0"', function () {
    expect(executeParse('non-falsy-string', '0'))->toBeFailure();
    expect(executeParse('truthy-string', '0'))->toBeFailure();
});

test('both reject the empty string', function (string $type) {
    expect(executeParse($type, ''))->toBeFailure();
})->with(['non-empty-string', 'non-falsy-string', 'truthy-string']);

test('both accept an ordinary string', function (string $type) {
    expect(executeParse($type, 'hello'))->toBeSuccess();
})->with(['non-empty-string', 'non-falsy-string', 'truthy-string']);

test('both reject a non-string', function (string $type) {
    expect(executeParse($type, 42))->toBeFailure();
})->with(['non-empty-string', 'non-falsy-string', 'truthy-string']);

// Every other constraint reports through the IssueMessage enum; a raw string key leaks a
// translation key that consumers cannot discover from the contract.
test('the non-empty-string failure reports an IssueMessage, not a raw key', function () {
    $result = executeParse('non-empty-string', '');
    $keys = array_map(
        fn($issue) => $issue->messageOrLocalizationKey,
        $result->issues->allFlat(),
    );

    expect($keys)->toContain(IssueMessage::NOT_EMPTY_STRING->value);
});
