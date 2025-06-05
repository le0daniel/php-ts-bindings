<?php

namespace Tests\Unit;

use Le0daniel\PhpTsBindings\Parser\TokenType;
use Le0daniel\PhpTsBindings\Parser\TypeStringTokenizer;

test('tokenize', function () {
    $tokenizer = new TypeStringTokenizer();
    expect($tokenizer->tokenize("string|int"))->toHaveCount(4)
        ->and($tokenizer->tokenize("string | int"))->toHaveCount(4)
        ->and($tokenizer->tokenize("string | int[]"))->toHaveCount(5)
        ->and($tokenizer->tokenize("string|int[]"))->toHaveCount(5)
        ->and($tokenizer->tokenize("string|int[]|object{name: 5}"))->toHaveCount(12)
        ->and($tokenizer->tokenize("string::class"))->toHaveCount(4);
});

test("0 Values caught correctly", function () {
    $tokenizer = new TypeStringTokenizer();

    $tokens = $tokenizer->tokenize("string|0|array{0: string, 1: string}");

    expect($tokens->at(2)->is(TokenType::INT))->toBeTrue();
    expect($tokens->at(2)->value)->toBe("0");
    expect($tokens->at(6)->is(TokenType::INT))->toBeTrue();
    expect($tokens->at(6)->value)->toBe("0");
});