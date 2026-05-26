<?php declare(strict_types=1);

namespace Tests\Unit\Parser\Consumers;

use Le0daniel\PhpTsBindings\Parser\Consumers\EnumConsumer;
use Le0daniel\PhpTsBindings\Parser\Data\ParsingContext;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\EnumNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Parser\TypeStringTokenizer;
use Tests\Mocks\ResultEnum;

function enumConsumerStateFor(string $typeString, ParsingContext $context = new ParsingContext()): ParserState
{
    return new ParserState($typeString, (new TypeStringTokenizer())->tokenize($typeString), $context);
}

test('EnumConsumer canConsume returns true for an enum identifier', function () {
    $state = enumConsumerStateFor(ResultEnum::class);
    expect((new EnumConsumer())->canConsume($state))->toBeTrue();
});

test('EnumConsumer canConsume returns false for a non-enum identifier', function () {
    $state = enumConsumerStateFor(\stdClass::class);
    expect((new EnumConsumer())->canConsume($state))->toBeFalse();
});

test('EnumConsumer canConsume returns false for a built-in keyword', function () {
    $state = enumConsumerStateFor('string');
    expect((new EnumConsumer())->canConsume($state))->toBeFalse();
});

test('EnumConsumer consume produces an EnumNode with the fully qualified class name', function () {
    $state = enumConsumerStateFor(ResultEnum::class);
    $node = (new EnumConsumer())->consume($state, new TypeParser());

    expect($node)
        ->toBeInstanceOf(EnumNode::class)
        ->and((string)$node)->toBe('enum<' . ResultEnum::class . '>');
});

test('EnumConsumer consume resolves an unqualified name against the ParsingContext namespace map', function () {
    $context = new ParsingContext(usedNamespaceMap: ['ResultEnum' => ResultEnum::class]);
    $state = enumConsumerStateFor('ResultEnum', $context);
    $node = (new EnumConsumer())->consume($state, new TypeParser());

    expect($node)
        ->toBeInstanceOf(EnumNode::class)
        ->and((string)$node)->toBe('enum<' . ResultEnum::class . '>');
});
