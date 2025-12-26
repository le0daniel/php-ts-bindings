<?php

namespace Tests\Unit\Server\Data\Exceptions;

use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidInputException;

test('create from messages', function () {

    $throwable = InvalidInputException::createFromMessages([
        'firstName' => 'Expected string',
        'lastName' => ['required', 'string']
    ]);

    expect($throwable)->tobeInstanceOf(InvalidInputException::class)
        ->and($throwable->failure->issues->serializeToFieldsArray())
        ->toBe([
            'firstName' => [
                'Expected string',
            ],
            'lastName' => [
                'required', 'string',
            ],
        ]);
});