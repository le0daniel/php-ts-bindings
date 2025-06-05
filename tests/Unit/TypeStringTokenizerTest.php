<?php

namespace Tests\Unit;

use Le0daniel\PhpTsBindings\Parser\TypeStringTokenizer;

test('tokenize', function () {

    $tokenizer = new TypeStringTokenizer();
    expect($tokenizer->tokenize("string|int"))->toHaveCount(4);
    expect($tokenizer->tokenize("string | int"))->toHaveCount(4);
    expect($tokenizer->tokenize("string | int[]"))->toHaveCount(5);
    expect($tokenizer->tokenize("string|int[]"))->toHaveCount(5);
    expect($tokenizer->tokenize("string|int[]|object{name: 5}"))->toHaveCount(12);
});