<?php

declare(strict_types=1);

use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Parser\Helpers\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\Helpers\Registry\CachedTypeRegistry;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\PropertyType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\IntNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\StringNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ReferencedNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

/**
 * Struct properties are canonically ordered at construction. That gives two guarantees: an
 * optimized AST behaves identically to a freshly parsed one (no dev/prod divergence), and two
 * declarations of the same shape in different orders intern to a single registry entry.
 */
function structOf(string ...$names): StructNode
{
    return new StructNode(
        StructPhpType::ARRAY,
        array_map(static fn (string $name) => new PropertyNode($name, new StringNode(), false), $names),
    );
}

test('property declaration order does not change a struct identity', function () {
    expect(structOf('zebra', 'alpha', 'middle')->exportPhpCode())
        ->toBe(structOf('alpha', 'middle', 'zebra')->exportPhpCode());
});

test('shapes declared in different orders intern to one entry', function () {
    $code = new ASTOptimizer()->generateOptimizedCode([
        'a' => new TypeParser()->parse('array{name: string, firstName: string}'),
        'b' => new TypeParser()->parse('array{firstName: string, name: string}'),
    ]);

    expect(preg_match_all('/\'#s[a-f0-9]+\' =>/', $code))
        ->toBe(1, 'The same shape declared in two orders must produce exactly one struct entry.');
});

test('properties are ordered by name', function () {
    $properties = structOf('zebra', 'alpha', 'middle')->properties;

    expect(array_map(static fn (PropertyNode $p) => $p->name, $properties))
        ->toBe(['alpha', 'middle', 'zebra']);
});

test('properties sharing a name are ordered by property type', function () {
    $struct = new StructNode(StructPhpType::ARRAY, [
        new PropertyNode('field', new StringNode(), false, PropertyType::OUTPUT),
        new PropertyNode('field', new IntNode(), false, PropertyType::INPUT),
    ]);

    expect(array_map(static fn (PropertyNode $p) => $p->propertyType, $struct->properties))
        ->toBe([PropertyType::INPUT, PropertyType::OUTPUT]);
});

test('a struct built from references is left untouched', function () {
    // ReferencedNode has no ->name; the optimizer rebuilds structs by mapping over an already
    // canonical list, so ordering must not be attempted here.
    $references = [
        new ReferencedNode('#pzzz', 'zebra: string', 'registry'),
        new ReferencedNode('#paaa', 'alpha: string', 'registry'),
    ];

    $struct = new StructNode(StructPhpType::ARRAY, $references);

    expect($struct->properties)->toBe($references);
});

test('filter and map preserve canonical order', function () {
    $struct = structOf('zebra', 'alpha', 'middle');

    $mapped = $struct->map(static fn (PropertyNode $p) => $p->changePropertyType(PropertyType::INPUT));
    $filtered = $struct->filter(static fn (PropertyNode $p) => $p->name !== 'middle');

    expect(array_map(static fn (PropertyNode $p) => $p->name, $mapped->properties))
        ->toBe(['alpha', 'middle', 'zebra'])
        ->and(array_map(static fn (PropertyNode $p) => $p->name, $filtered->properties))
        ->toBe(['alpha', 'zebra']);
});

test('C5: an optimized struct serializes in the same key order as the parsed one', function () {
    $node = new TypeParser()->parse('array{zebra: string, alpha: string, middle: int}');
    $code = new ASTOptimizer()->generateOptimizedCode(['node' => $node]);

    /** @var CachedTypeRegistry $registry */
    $registry = eval("return {$code};");

    $executor = new SchemaExecutor();
    $data = ['zebra' => 'z', 'alpha' => 'a', 'middle' => 1];

    expect(json_encode($executor->serialize($registry->get('node'), $data)->value, JSON_THROW_ON_ERROR))
        ->toBe(json_encode($executor->serialize($node, $data)->value, JSON_THROW_ON_ERROR));
});

test('C5: key order is stable without any external sorting pass', function () {
    $node = new TypeParser()->parse('array{zebra: string, alpha: string, middle: int}');
    $executor = new SchemaExecutor();

    expect(json_encode($executor->serialize($node, ['zebra' => 'z', 'alpha' => 'a', 'middle' => 1])->value, JSON_THROW_ON_ERROR))
        ->toBe('{"alpha":"a","middle":1,"zebra":"z"}');
});
