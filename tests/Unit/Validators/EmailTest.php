<?php

namespace Tests\Unit\Validators;

use Le0daniel\PhpTsBindings\Contracts\Constraint;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Validators\Email;

/** @return array{bool, Issue[]} */
function validate(mixed $value, Constraint $constraint): array
{
    $context = new Context();
    $result = $constraint->validate($value, $context);
    return [$result, $context->getIssuesAt(Context::ROOT_PATH)];
}

test('validate invalid string email', function () {
    $email = new Email();

    [$result, $issues] = validate('some value', $email);

    expect($result)->toBeFalse();
    expect($issues)->toHaveCount(1);
    expect($issues[0]->messageOrLocalizationKey)->toBe(IssueMessage::INVALID_EMAIL->value);
});

test('validate valid string email', function () {
    $email = new Email();

    [$result, $issues] = validate('leo@test.test', $email);

    expect($result)->toBeTrue();
    expect($issues)->toHaveCount(0);
});

test('invalid data type', function (mixed $value) {
    $email = new Email();

    [$result, $issues] = validate($value, $email);

    expect($result)->toBeFalse();
    expect($issues)->toHaveCount(1);
    expect($issues[0]->messageOrLocalizationKey)->toBe(IssueMessage::INVALID_TYPE->value);
})->with([
    [123,],
    [-0.34,],
    [[],],
    [['value'],],
    [(object) [],],
    [(object) ['key' => 'value'],],
    [false,],
    [true,],
    [null,],
]);