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
        ->and($tokenizer->tokenize("string::class"))->toHaveCount(2);
});

test("0 Values caught correctly", function () {
    $tokenizer = new TypeStringTokenizer();

    $tokens = $tokenizer->tokenize("string|0|array{0: string, 1: string}");

    expect($tokens->at(2)->is(TokenType::INT))->toBeTrue();
    expect($tokens->at(2)->value)->toBe("0");
    expect($tokens->at(6)->is(TokenType::INT))->toBeTrue();
    expect($tokens->at(6)->value)->toBe("0");
});

test("Identifies Class Const correctly", function () {
    $tokenizer = new TypeStringTokenizer();

    $tokens = $tokenizer->tokenize("string|0|Value::INVALID|array{0: string, 1: string}");

    expect($tokens->at(4)->is(TokenType::CLASS_CONST))->toBeTrue();
    expect($tokens->at(4)->value)->toBe("Value::INVALID");
    expect($tokens)->toHaveCount(17);
});

test("positive and negative numbers", function () {
    $tokenizer = new TypeStringTokenizer();
    $tokens = $tokenizer->tokenize("0|1|-1|0.1|-0.3");
    foreach ($tokens as $index => $token) {
        match ($index) {
            0 => expect($token->is(TokenType::INT, '0'))->toBeTrue(),
            1 => expect($token->is(TokenType::PIPE))->toBeTrue(),
            2 => expect($token->is(TokenType::INT, '1'))->toBeTrue(),
            3 => expect($token->is(TokenType::PIPE))->toBeTrue(),
            4 => expect($token->is(TokenType::INT, '-1'))->toBeTrue(),
            5 => expect($token->is(TokenType::PIPE))->toBeTrue(),
            6 => expect($token->is(TokenType::FLOAT, '0.1'))->toBeTrue(),
            7 => expect($token->is(TokenType::PIPE))->toBeTrue(),
            8 => expect($token->is(TokenType::FLOAT, '-0.3'))->toBeTrue(),
            default => expect($token->is(TokenType::EOF))->toBeTrue(),
        };
    }
});

test("Test tokenizer with complex string", function () {
    $tokenizer = new TypeStringTokenizer();
    $tokens = $tokenizer->tokenize("string|0|Value::INVALID_INVALID|array{0: string, 1: string}|object{name: 'leo'}");

    foreach ($tokens as $index => $token) {
        match ($index) {
            0 => expect($token->is(TokenType::IDENTIFIER, 'string'))->toBeTrue(),
            1 => expect($token->is(TokenType::PIPE))->toBeTrue(),
            2 => expect($token->is(TokenType::INT, '0'))->toBeTrue(),
            3 => expect($token->is(TokenType::PIPE))->toBeTrue(),
            4 => expect($token->is(TokenType::CLASS_CONST, 'Value::INVALID_INVALID'))->toBeTrue(),
            5 => expect($token->is(TokenType::PIPE))->toBeTrue(),
            6 => expect($token->is(TokenType::IDENTIFIER, 'array'))->toBeTrue(),
            7 => expect($token->is(TokenType::LBRACE))->toBeTrue(),
            8 => expect($token->is(TokenType::INT, '0'))->toBeTrue(),
            9 => expect($token->is(TokenType::COLON))->toBeTrue(),
            10 => expect($token->is(TokenType::IDENTIFIER, 'string'))->toBeTrue(),
            11 => expect($token->is(TokenType::COMMA))->toBeTrue(),
            12 => expect($token->is(TokenType::INT, '1'))->toBeTrue(),
            13 => expect($token->is(TokenType::COLON))->toBeTrue(),
            14 => expect($token->is(TokenType::IDENTIFIER, 'string'))->toBeTrue(),
            15 => expect($token->is(TokenType::RBRACE))->toBeTrue(),
            16 => expect($token->is(TokenType::PIPE))->toBeTrue(),
            17 => expect($token->is(TokenType::IDENTIFIER, 'object'))->toBeTrue(),
            18 => expect($token->is(TokenType::LBRACE))->toBeTrue(),
            19 => expect($token->is(TokenType::IDENTIFIER, 'name'))->toBeTrue(),
            20 => expect($token->is(TokenType::COLON))->toBeTrue(),
            21 => expect($token->is(TokenType::STRING, 'leo'))->toBeTrue(),
            22 => expect($token->is(TokenType::RBRACE))->toBeTrue(),
            default => expect($token->is(TokenType::EOF))->toBeTrue(),
        };
    }
});