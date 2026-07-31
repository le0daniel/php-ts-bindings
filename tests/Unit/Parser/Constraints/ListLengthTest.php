<?php declare(strict_types=1);

namespace Tests\Unit\Parser\Constraints;

use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Issues;
use Le0daniel\PhpTsBindings\Parser\Constraints\ListLength;

beforeEach(function () {
    $this->context = new Context();
});

it('counts elements against an inclusive range', function () {
    $constraint = new ListLength(min: 1, max: 3);

    expect($constraint->validate([], $this->context))->toBeFalse()
        ->and($constraint->validate([1], $this->context))->toBeTrue()
        ->and($constraint->validate([1, 2, 3], $this->context))->toBeTrue()
        ->and($constraint->validate([1, 2, 3, 4], $this->context))->toBeFalse();
});

// non-empty-array<string, V> parses to a RecordNode, which is a string keyed PHP array.
it('counts a string keyed array the same way', function () {
    $constraint = new ListLength(min: 1);

    expect($constraint->validate([], $this->context))->toBeFalse()
        ->and($constraint->validate(['a' => 1], $this->context))->toBeTrue();
});

it('rejects everything that is not an array', function (mixed $value) {
    $constraint = new ListLength(min: 1, max: 5);

    expect($constraint->validate($value, $this->context))->toBeFalse();
})->with([
    ['ab'],
    [3],
    [true],
    [null],
    [new \stdClass()],
]);

it('reports the bound that failed', function () {
    $constraint = new ListLength(min: 2, max: 3);

    $context = new Context();
    $constraint->validate([1], $context);
    expect($context->issues[Issues::ROOT_PATH][0]->messageOrLocalizationKey)->toBe('validation.invalid_min');

    $context = new Context();
    $constraint->validate([1, 2, 3, 4], $context);
    expect($context->issues[Issues::ROOT_PATH][0]->messageOrLocalizationKey)->toBe('validation.invalid_max');

    $context = new Context();
    $constraint->validate('nope', $context);
    expect($context->issues[Issues::ROOT_PATH][0]->messageOrLocalizationKey)->toBe('validation.invalid_type');
});

it('exports PHP code correctly', function () {
    expect(new ListLength(min: 1)->exportPhpCode())
        ->toBe('new \\' . ListLength::class . '(1,NULL)');
});

it('names its bounds in diagnostics', function () {
    expect((string)new ListLength(min: 1))->toBe('ListLength(1, max)');
});
