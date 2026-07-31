<?php declare(strict_types=1);

use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\NamedType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\StringNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\MetadataNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Parser\Constraints\NonEmptyString;

/**
 * __toString() is the label a developer sees in error messages and debug output. It no longer
 * feeds the schema cache — identity comes from exportPhpCode() — so it is free to describe a node
 * accurately rather than being constrained to match its inner node.
 */

test('a constrained node names its constraints instead of hiding them', function () {
    $node = new ConstraintNode(new StringNode(), [new NonEmptyString()]);

    expect((string)$node)->not->toBe('string')
        ->and((string)$node)->toContain('string')
        ->and((string)$node)->toContain('NonEmptyString');
});

test('a constraint free node reads as its inner node', function () {
    expect((string)new ConstraintNode(new StringNode(), []))->toBe('string');
});

test('integer and float literals are distinguishable', function () {
    expect((string)new LiteralNode(LiteralType::INT, 1))
        ->not->toBe((string)new LiteralNode(LiteralType::FLOAT, 1.0));
});

test('a tuple keeps its braces', function () {
    expect((string)new TypeParser()->parse('array{0: string, 1: int}'))
        ->toBe('array{0: string, 1: int}');
});

test('a discriminated union names its discriminator', function () {
    $discriminated = new TypeParser()->parse("array{kind: 'a', v: string}|array{kind: 'b', v: int}");
    $plain = new Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode($discriminated->nodes);

    expect((string)$discriminated)->toContain('kind')
        ->and((string)$discriminated)->not->toBe((string)$plain);
});

test('metadata stays transparent, which the elimination guarantee depends on', function () {
    $inner = new StringNode();
    $node = new MetadataNode($inner, new NamedType('Token'), 'token');

    expect((string)$node)->toBe((string)$inner);
});
