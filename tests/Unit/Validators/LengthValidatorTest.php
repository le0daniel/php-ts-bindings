<?php

namespace Tests\Unit\Validators;

use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Validators\LengthValidator;

beforeEach(function () {
    $this->context = new Context();
});

it('validates string length correctly', function () {
    $validator = new LengthValidator(min: 2, max: 5);

    expect($validator->validate('a', $this->context))->toBeFalse()
        ->and($validator->validate('ab', $this->context))->toBeTrue()
        ->and($validator->validate('abcd', $this->context))->toBeTrue()
        ->and($validator->validate('abcdef', $this->context))->toBeFalse();
});

it('validates array count correctly', function () {
    $validator = new LengthValidator(min: 1, max: 3);

    expect($validator->validate([], $this->context))->toBeFalse()
        ->and($validator->validate([1], $this->context))->toBeTrue()
        ->and($validator->validate([1, 2, 3], $this->context))->toBeTrue()
        ->and($validator->validate([1, 2, 3, 4], $this->context))->toBeFalse();
});

it('validates integer values directly', function () {
    $validator = new LengthValidator(min: 5, max: 10);

    expect($validator->validate(4, $this->context))->toBeFalse()
        ->and($validator->validate(5, $this->context))->toBeTrue()
        ->and($validator->validate(7, $this->context))->toBeTrue()
        ->and($validator->validate(10, $this->context))->toBeTrue()
        ->and($validator->validate(11, $this->context))->toBeFalse();
});

it('handles non-including boundaries correctly', function () {
    $validator = new LengthValidator(min: 5, max: 10, including: false);

    expect($validator->validate(5, $this->context))->toBeFalse()
        ->and($validator->validate(6, $this->context))->toBeTrue()
        ->and($validator->validate(9, $this->context))->toBeTrue()
        ->and($validator->validate(10, $this->context))->toBeFalse();
});

it('handles null min correctly', function () {
    $validator = new LengthValidator(max: 5);

    expect($validator->validate(1, $this->context))->toBeTrue()
        ->and($validator->validate(5, $this->context))->toBeTrue()
        ->and($validator->validate(6, $this->context))->toBeFalse();
});

it('handles null max correctly', function () {
    $validator = new LengthValidator(min: 5);

    expect($validator->validate(4, $this->context))->toBeFalse()
        ->and($validator->validate(5, $this->context))->toBeTrue()
        ->and($validator->validate(100, $this->context))->toBeTrue();
});

it('returns false for invalid types', function () {
    $validator = new LengthValidator(min: 1, max: 5);

    expect($validator->validate(null, $this->context))->toBeFalse()
        ->and($validator->validate(new \stdClass(), $this->context))->toBeFalse()
        ->and($validator->validate(true, $this->context))->toBeFalse();
});

it('exports PHP code correctly', function () {
    $validator = new LengthValidator(min: 5, max: 10, including: false);
    $expected = 'new \\' . LengthValidator::class . '(5, 10, false)';
    expect($validator->exportPhpCode())->toBe($expected);
});

it('adds correct validation issues to context', function () {
    $validator = new LengthValidator(min: 2, max: 4);
    $context = new Context();

    // Test invalid type
    $validator->validate(null, $context);
    expect($context->issues[Context::ROOT_PATH])->toHaveCount(1)
        ->and($context->issues[Context::ROOT_PATH][0]->messageOrLocalizationKey)->toBe('validation.invalid_type')
        ->and($context->issues[Context::ROOT_PATH][0]->debugInfo)->toHaveKey('message')
        ->and($context->issues[Context::ROOT_PATH][0]->debugInfo['message'])->toContain('Wrong type for length validation');

    // Reset context
    $context = new Context();

    // Test min validation
    $validator->validate('a', $context);
    expect($context->issues[Context::ROOT_PATH])->toHaveCount(1)
        ->and($context->issues[Context::ROOT_PATH][0]->messageOrLocalizationKey)->toBe('validation.invalid_min')
        ->and($context->issues[Context::ROOT_PATH][0]->debugInfo)->toHaveKey('message')
        ->and($context->issues[Context::ROOT_PATH][0]->debugInfo['message'])->toContain('Expected value to be at least 2 characters long');

    // Reset context
    $context = new Context();

    // Test max validation
    $validator->validate('abcde', $context);
    expect($context->issues[Context::ROOT_PATH])->toHaveCount(1)
        ->and($context->issues[Context::ROOT_PATH][0]->messageOrLocalizationKey)->toBe('validation.invalid_max')
        ->and($context->issues[Context::ROOT_PATH][0]->debugInfo)->toHaveKey('message')
        ->and($context->issues[Context::ROOT_PATH][0]->debugInfo['message'])->toContain('Expected value to be at most 4 characters long');

    // Reset context
    $context = new Context();

    // Test valid input (should not add any issues)
    $validator->validate('abc', $context);
    expect($context->issues)->toBeEmpty();
});

