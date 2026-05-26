<?php declare(strict_types=1);

namespace Tests\Unit\Parser\Consumers;

use Le0daniel\PhpTsBindings\Parser\Consumers\DateTimeConsumer;
use Le0daniel\PhpTsBindings\Parser\Data\ParsingContext;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\DateTimeNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Parser\TypeStringTokenizer;

function dateTimeConsumerStateFor(string $typeString, ParsingContext $context = new ParsingContext()): ParserState
{
    return new ParserState($typeString, (new TypeStringTokenizer())->tokenize($typeString), $context);
}

test('DateTimeConsumer canConsume returns true for \\DateTime without a namespace', function () {
    $state = dateTimeConsumerStateFor(\DateTime::class);
    expect((new DateTimeConsumer())->canConsume($state))->toBeTrue();
});

test('DateTimeConsumer canConsume returns true for \\DateTimeImmutable without a namespace', function () {
    $state = dateTimeConsumerStateFor(\DateTimeImmutable::class);
    expect((new DateTimeConsumer())->canConsume($state))->toBeTrue();
});

test('DateTimeConsumer canConsume returns true for DateTime inside a namespace context (fallback)', function () {
    $state = dateTimeConsumerStateFor('DateTime', new ParsingContext('Some\\Namespace'));
    expect((new DateTimeConsumer())->canConsume($state))->toBeTrue();
});

test('DateTimeConsumer canConsume returns false for a non-DateTime class', function () {
    $state = dateTimeConsumerStateFor(\stdClass::class);
    expect((new DateTimeConsumer())->canConsume($state))->toBeFalse();
});

test('DateTimeConsumer canConsume returns false for a built-in keyword', function () {
    $state = dateTimeConsumerStateFor('string');
    expect((new DateTimeConsumer())->canConsume($state))->toBeFalse();
});

test('DateTimeConsumer consume returns a DateTimeNode with the correct class name', function () {
    $state = dateTimeConsumerStateFor(\DateTime::class);
    $node = (new DateTimeConsumer())->consume($state, new TypeParser());

    expect($node)
        ->toBeInstanceOf(DateTimeNode::class)
        ->and($node->dateTimeClass)->toBe(\DateTime::class);
});

test('DateTimeConsumer consume preserves the class name through the namespace fallback', function () {
    $state = dateTimeConsumerStateFor('DateTime', new ParsingContext('Some\\Namespace'));
    $node = (new DateTimeConsumer())->consume($state, new TypeParser());

    expect($node)
        ->toBeInstanceOf(DateTimeNode::class)
        ->and($node->dateTimeClass)->toBe(\DateTime::class);
});
