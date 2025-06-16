<?php

namespace Tests\Unit\Utils;

use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BuiltInType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BuiltInNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Utils\Nodes;

test('are all nodes of same struct type', function () {
    expect(Nodes::areAllNodesOfSameStructType([
        new ConstraintNode(
            new StructNode(
                StructPhpType::ARRAY,
                [new PropertyNode('name', new BuiltInNode(BuiltInType::STRING), false)]
            ),
            [],
        ),
        new StructNode(
            StructPhpType::ARRAY,
            [new PropertyNode('name', new BuiltInNode(BuiltInType::STRING), false)]
        ),
    ]))->toBeTrue();
});

test('Not all nodes have the same struct type', function () {
    expect(Nodes::areAllNodesOfSameStructType([
        new ConstraintNode(
            new StructNode(
                StructPhpType::OBJECT,
                [new PropertyNode('name', new BuiltInNode(BuiltInType::STRING), false)]
            ),
            [],
        ),
        new StructNode(
            StructPhpType::ARRAY,
            [new PropertyNode('name', new BuiltInNode(BuiltInType::STRING), false)]
        ),
    ]))->toBeFalse();
});

test('Not all nodes are struct nodes', function () {
    expect(Nodes::areAllNodesOfSameStructType([
        new ConstraintNode(
            new BuiltInNode(BuiltInType::STRING),
            [],
        ),
        new StructNode(
            StructPhpType::ARRAY,
            [new PropertyNode('name', new BuiltInNode(BuiltInType::STRING), false)]
        ),
    ]))->toBeFalse();
});

test('Same nodes, one level deep', function () {
    expect(Nodes::areAllNodesOfSameStructType([
        new StructNode(
            StructPhpType::ARRAY,
            [new PropertyNode('other', new BuiltInNode(BuiltInType::STRING), false)]
        ),
        new StructNode(
            StructPhpType::ARRAY,
            [new PropertyNode('name', new BuiltInNode(BuiltInType::STRING), false)]
        ),
    ]))->toBeTrue();
});