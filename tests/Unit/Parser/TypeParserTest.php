<?php

namespace Tests\Unit\Parser;

use Le0daniel\PhpTsBindings\Data\AvailableNamespaces;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BuiltInType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BuiltInNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Parser\TypeStringTokenizer;
use Tests\Mocks\ResultEnum;


test('test simple union', function () {
    $parser = new TypeParser(new TypeStringTokenizer());

    expect($node = $parser->parse("string | int"))
        ->toBeInstanceOf(UnionNode::class);

    compareToOptimizedAst($node);
});

test('test scalar', function () {
    $parser = new TypeParser(new TypeStringTokenizer());

    expect($node = $parser->parse("scalar"))
        ->toBeInstanceOf(UnionNode::class);

    compareToOptimizedAst($node);
});

test('positive-int', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var ConstraintNode $node */
    $node = $parser->parse("positive-int");

    expect($node)->toBeInstanceOf(ConstraintNode::class);
    expect($node->node)->toBeInstanceOf(BuiltInNode::class);
    expect($node->node->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('non-negative-int', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var ConstraintNode $node */
    $node = $parser->parse("non-negative-int");

    expect($node)->toBeInstanceOf(ConstraintNode::class);
    expect($node->node)->toBeInstanceOf(BuiltInNode::class);
    expect($node->node->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('non-positive-int', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var ConstraintNode $node */
    $node = $parser->parse("non-positive-int");

    expect($node)->toBeInstanceOf(ConstraintNode::class);
    expect($node->node)->toBeInstanceOf(BuiltInNode::class);
    expect($node->node->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('negative-int', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var ConstraintNode $node */
    $node = $parser->parse("negative-int");

    expect($node)->toBeInstanceOf(ConstraintNode::class);
    expect($node->node)->toBeInstanceOf(BuiltInNode::class);
    expect($node->node->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('object struct', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var StructNode $node */
    $node = $parser->parse("object{a: string, b: int}");
    expect($node)->toBeInstanceOf(StructNode::class);

    expect($node->phpType)->toEqual(StructPhpType::OBJECT);
    expect($node->getProperty('a')->type)->toBeInstanceOf(BuiltInNode::class);
    expect($node->getProperty('a')->type->type)->toEqual(BuiltInType::STRING);

    expect($node->getProperty('b')->type)->toBeInstanceOf(BuiltInNode::class);
    expect($node->getProperty('b')->type->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('array struct', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var StructNode $node */
    $node = $parser->parse("array{a: string, b: int}");
    expect($node)->toBeInstanceOf(StructNode::class);

    expect($node->phpType)->toEqual(StructPhpType::ARRAY);
    expect($node->getProperty('a')->type)->toBeInstanceOf(BuiltInNode::class);
    expect($node->getProperty('a')->type->type)->toEqual(BuiltInType::STRING);

    expect($node->getProperty('b')->type)->toBeInstanceOf(BuiltInNode::class);
    expect($node->getProperty('b')->type->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('simplified tuple struct', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var TupleNode $node */
    $node = $parser->parse("array{string, int}");
    expect($node)->toBeInstanceOf(TupleNode::class);

    expect($node->types[0])->toBeInstanceOf(BuiltInNode::class);
    expect($node->types[0]->type)->toEqual(BuiltInType::STRING);

    expect($node->types[1])->toBeInstanceOf(BuiltInNode::class);
    expect($node->types[1]->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('classic tuple struct', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var TupleNode $node */
    $node = $parser->parse("array{0:string, 1: int}");
    expect($node)->toBeInstanceOf(TupleNode::class);

    expect($node->types[0])->toBeInstanceOf(BuiltInNode::class);
    expect($node->types[0]->type)->toEqual(BuiltInType::STRING);

    expect($node->types[1])->toBeInstanceOf(BuiltInNode::class);
    expect($node->types[1]->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('List struct', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var ListNode $node */
    $node = $parser->parse("array<string>");
    expect($node)->toBeInstanceOf(ListNode::class);

    expect($node->type)->toBeInstanceOf(BuiltInNode::class);
    expect($node->type->type)->toEqual(BuiltInType::STRING);

    compareToOptimizedAst($node);
});

test('Record struct', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var RecordNode $node */
    $node = $parser->parse("array<string, int>");
    expect($node)->toBeInstanceOf(RecordNode::class);

    expect($node->type)->toBeInstanceOf(BuiltInNode::class);
    expect($node->type->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('Test simple literals', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var UnionNode $node */
    $node = $parser->parse("1|-2|true|false|'string'");
    expect($node)->toBeInstanceOf(UnionNode::class);

    foreach ($node->types as $index => $type) {
        match ($index) {
            0 => expect($type->value)->toBe(1),
            1 => expect($type->value)->toBe(-2),
            2 => expect($type->value)->toBe(true),
            3 => expect($type->value)->toBe(false),
            4 => expect($type->value)->toBe('string'),
            default => null,
        };
    }

    compareToOptimizedAst($node);
});

test('Test EnumCase literal', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var UnionNode $node */
    $node = $parser->parse("ResultEnumBase::SUCCESS|ResultEnumBase::FAILURE", new AvailableNamespaces(
        null,
        [
            'ResultEnumBase' => 'Tests\Mocks\ResultEnum'
        ]
    ));
    expect($node)->toBeInstanceOf(UnionNode::class);

    foreach ($node->types as $index => $type) {
        match ($index) {
            0 => expect($type->value)->toBe(ResultEnum::SUCCESS),
            1 => expect($type->value)->toBe(ResultEnum::FAILURE),
            default => null,
        };
    }

    compareToOptimizedAst($node);
});

