<?php

namespace Tests\Executor;

use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\ParsingOptions;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

test('test parsing with coersion', function () {
    $executor = new SchemaExecutor();
    $parser = new TypeParser();

    $result = $executor->parse(
        $parser->parse('int|bool'),
        '7',
        new ParsingOptions(coercePrimitives: true)
    );

    expect($result)->toBeInstanceOf(Success::class);

    $result = $executor->parse(
        $parser->parse('int|bool'),
        'true',
        new ParsingOptions(coercePrimitives: true)
    );

    expect($result)->toBeInstanceOf(Success::class);

    $result = $executor->parse(
        $parser->parse('int|bool'),
        '7',
        new ParsingOptions(coercePrimitives: false)
    );

    expect($result)->toBeInstanceOf(Failure::class);
});

test('coerce with literal', function () {
    $executor = new SchemaExecutor();
    $parser = new TypeParser();

    // Allow only specific literal values
    $type = $parser->parse('7|8|42|true|false');

    // Successes with coercion enabled (strings should coerce to matching literals)
    foreach (['7', '8', '42', 'true', 'false'] as $input) {
        $result = $executor->parse($type, $input, new ParsingOptions(coercePrimitives: true));
        expect($result)->toBeInstanceOf(Success::class);
    }

    // Failures with coercion enabled (values not in the literal union)
    foreach (['9', 'foo', '', 'TRUE'] as $input) {
        $result = $executor->parse($type, $input, new ParsingOptions(coercePrimitives: true));
        expect($result)->toBeInstanceOf(Failure::class);
    }

    // Failures with coercion disabled (string representations should not match literals)
    foreach (['7', '8', '42', 'true', 'false'] as $input) {
        $result = $executor->parse($type, $input, new ParsingOptions(coercePrimitives: false));
        expect($result)->toBeInstanceOf(Failure::class);
    }
});

test('DateTimeString (bare) parses an ATOM string to DateTimeImmutable and serializes back', function () {
    $executor = new SchemaExecutor();
    $parser = new TypeParser();

    $type = $parser->parse('DateTimeString');
    $atom = '2026-05-26T10:15:30+00:00';

    $parsed = $executor->parse($type, $atom);
    expect($parsed)->toBeInstanceOf(Success::class)
        ->and($parsed->value)->toBeInstanceOf(\DateTimeImmutable::class)
        ->and($parsed->value->format(\DateTimeInterface::ATOM))->toBe($atom);

    $serialized = $executor->serialize($type, $parsed->value);
    expect($serialized)->toBeInstanceOf(Success::class)
        ->and($serialized->value)->toBe($atom);
});

test('DateTimeString with a custom format parses and serializes using that format', function () {
    $executor = new SchemaExecutor();
    $parser = new TypeParser();

    $type = $parser->parse("DateTimeString<'Y-m-d'>");

    $parsed = $executor->parse($type, '2026-05-26');
    expect($parsed)->toBeInstanceOf(Success::class)
        ->and($parsed->value)->toBeInstanceOf(\DateTimeImmutable::class)
        ->and($parsed->value->format('Y-m-d'))->toBe('2026-05-26');

    $serialized = $executor->serialize($type, $parsed->value);
    expect($serialized)->toBeInstanceOf(Success::class)
        ->and($serialized->value)->toBe('2026-05-26');
});

test('DateTimeString rejects malformed input and non-string serialization targets', function () {
    $executor = new SchemaExecutor();
    $parser = new TypeParser();

    $type = $parser->parse("DateTimeString<'Y-m-d'>");

    expect($executor->parse($type, 'not-a-date'))->toBeInstanceOf(Failure::class);
    expect($executor->parse($type, 12345))->toBeInstanceOf(Failure::class);
    expect($executor->serialize($type, 'not-a-datetime'))->toBeInstanceOf(Failure::class);
});

test('DateTimeString serializes a mutable DateTime through the DateTimeInterface contract', function () {
    $executor = new SchemaExecutor();
    $parser = new TypeParser();

    $type = $parser->parse("DateTimeString<'Y-m-d'>");
    $mutable = new \DateTime('2026-05-26');

    $serialized = $executor->serialize($type, $mutable);
    expect($serialized)->toBeInstanceOf(Success::class)
        ->and($serialized->value)->toBe('2026-05-26');
});

