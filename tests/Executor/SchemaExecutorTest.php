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
    $result = $executor->parse(
        $parser->parse('7|true'),
        '7',
        new ParsingOptions(coercePrimitives: true)
    );

    expect($result)->toBeInstanceOf(Success::class);
});