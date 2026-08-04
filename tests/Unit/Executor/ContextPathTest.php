<?php declare(strict_types=1);

namespace Tests\Unit\Executor;

use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;

/**
 * removeCurrentIssues() is what lets a union discard the diagnostics of the arms it rejected. It
 * has to mean "this path and everything nested below it" - comparing the joined path as a raw
 * string means neither half of that holds.
 */

function issueAt(Context $context, string ...$path): void
{
    foreach ($path as $segment) {
        $context->enterPath($segment);
    }
    $context->addIssue(new Issue(IssueMessage::INVALID_TYPE));
    foreach ($path as $_) {
        $context->leavePath();
    }
}

test('removing issues at a path removes everything nested below it', function () {
    $context = new Context();
    $context->enterPath('user');
    $context->addIssue(new Issue(IssueMessage::INVALID_TYPE));
    $context->enterPath('name');
    $context->addIssue(new Issue(IssueMessage::INVALID_TYPE));
    $context->leavePath();

    $context->removeCurrentIssues();

    expect($context->issues)->toBe([]);
});

test('removing issues at a path leaves a sibling whose name merely starts the same', function () {
    // 'items.0' starts with the string 'item' but is not nested under it.
    $context = new Context();
    issueAt($context, 'items', '0');

    $context->enterPath('item');
    $context->removeCurrentIssues();
    $context->leavePath();

    expect($context->issues)->toHaveKey('items.0');
});

test('removing issues at the root removes nested issues', function () {
    // pathAsString() is '__root' at the top level, which is a prefix of no nested path at all.
    $context = new Context();
    issueAt($context, 'a');
    issueAt($context, 'a', 'b');
    $context->addIssue(new Issue(IssueMessage::INVALID_TYPE));

    $context->removeCurrentIssues();

    expect($context->issues)->toBe([]);
});

test('a numeric path segment is still matched', function () {
    // PHP coerces the array key '0' to int, which is why the comparison casts back to string.
    $context = new Context();
    issueAt($context, '0');

    $context->removeCurrentIssues();

    expect($context->issues)->toBe([]);
});
