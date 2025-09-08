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

