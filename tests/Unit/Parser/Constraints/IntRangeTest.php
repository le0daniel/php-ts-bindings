<?php declare(strict_types=1);

namespace Tests\Unit\Parser\Constraints;

use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Issues;
use Le0daniel\PhpTsBindings\Parser\Helpers\Constraints\IntRange;

beforeEach(function () {
    $this->context = new Context();
});

it('validates an inclusive range', function () {
    $constraint = new IntRange(min: 5, max: 10);

    expect($constraint->validate(4, $this->context))->toBeFalse()
        ->and($constraint->validate(5, $this->context))->toBeTrue()
        ->and($constraint->validate(7, $this->context))->toBeTrue()
        ->and($constraint->validate(10, $this->context))->toBeTrue()
        ->and($constraint->validate(11, $this->context))->toBeFalse();
});

it('treats a null bound as unbounded', function () {
    expect(new IntRange(max: 5)->validate(PHP_INT_MIN, $this->context))->toBeTrue()
        ->and(new IntRange(max: 5)->validate(6, $this->context))->toBeFalse()
        ->and(new IntRange(min: 5)->validate(PHP_INT_MAX, $this->context))->toBeTrue()
        ->and(new IntRange(min: 5)->validate(4, $this->context))->toBeFalse();
});

/**
 * The Length constraint this replaced dispatched on gettype(), so an int range happily accepted a
 * string of the right character count and an array of the right element count. A constraint now
 * owns exactly one PHP type.
 */
it('rejects everything that is not an int', function (mixed $value) {
    $constraint = new IntRange(min: 1, max: 5);

    expect($constraint->validate($value, $this->context))->toBeFalse();
})->with([
    ['abc'],
    ['5'],
    [[1, 2, 3]],
    [3.0],
    [true],
    [null],
    [new \stdClass()],
]);

it('reports the bound that failed', function () {
    $constraint = new IntRange(min: 2, max: 4);

    $context = new Context();
    $constraint->validate(1, $context);
    expect($context->issues[Issues::ROOT_PATH][0]->messageOrLocalizationKey)->toBe('validation.invalid_min');

    $context = new Context();
    $constraint->validate(5, $context);
    expect($context->issues[Issues::ROOT_PATH][0]->messageOrLocalizationKey)->toBe('validation.invalid_max');

    $context = new Context();
    $constraint->validate('nope', $context);
    expect($context->issues[Issues::ROOT_PATH][0]->messageOrLocalizationKey)->toBe('validation.invalid_type');

    $context = new Context();
    $constraint->validate(3, $context);
    expect($context->issues)->toBeEmpty();
});

it('exports PHP code correctly', function () {
    expect(new IntRange(min: 5, max: 10)->exportPhpCode())
        ->toBe('new \\' . IntRange::class . '(5,10)')
        ->and(new IntRange(min: 1)->exportPhpCode())
        ->toBe('new \\' . IntRange::class . '(1,NULL)');
});

it('names its bounds in diagnostics', function () {
    expect((string)new IntRange(0, 100))->toBe('IntRange(0, 100)')
        ->and((string)new IntRange(min: 1))->toBe('IntRange(1, max)')
        ->and((string)new IntRange(max: -1))->toBe('IntRange(min, -1)');
});
