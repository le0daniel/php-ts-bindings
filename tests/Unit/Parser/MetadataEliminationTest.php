<?php declare(strict_types=1);

use Le0daniel\PhpTsBindings\Parser\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\NamedType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\IntNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\StringNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\MetadataNode;
use Le0daniel\PhpTsBindings\Parser\Registry\CachedTypeRegistry;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Utils\Nodes;
use Tests\Mocks\Named\Customer;
use Tests\Mocks\ValueObjects\Email;

/**
 * Codegen metadata must never reach a cached AST. The guarantee is doubled: ASTOptimizer strips
 * MetadataNode while interning, and MetadataNode::exportPhpCode() delegates to its inner node so
 * the wrapper is structurally unserializable. These tests pin both halves.
 */

/**
 * @return array{code: string, node: NodeInterface}
 */
function optimizeSingle(NodeInterface $node): array
{
    $code = new ASTOptimizer()->generateOptimizedCode(['node' => $node]);

    /** @var CachedTypeRegistry $registry */
    $registry = eval("return {$code};");

    return ['code' => $code, 'node' => $registry->get('node')];
}

function containsMetadataNode(NodeInterface $node): bool
{
    $stack = [$node];
    while ($current = array_pop($stack)) {
        if ($current instanceof MetadataNode) {
            return true;
        }

        foreach (new ReflectionObject($current)->getProperties() as $property) {
            // UnionNode::$acceptsNull is a lazily populated memo and may be uninitialized.
            if (!$property->isInitialized($current)) {
                continue;
            }

            $value = $property->getValue($current);
            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof NodeInterface) {
                    $stack[] = $child;
                }
            }
        }
    }

    return false;
}

/**
 * Every structural position a MetadataNode can occupy. Each entry must survive parsing with
 * metadata present and come out of the optimizer with none.
 */
dataset('metadata positions', [
    'bare branded utility' => "BrandedString<'tok'>",
    'struct property' => "array{t: BrandedString<'tok'>}",
    'list element' => "list<BrandedString<'tok'>>",
    'record value' => "array<string, BrandedString<'tok'>>",
    'union member' => "BrandedString<'tok'>|int",
    'tuple element' => "array{0: BrandedString<'tok'>, 1: int}",
    'deeply nested' => "array{a: array{b: list<BrandedString<'tok'>>}}",
    'named class' => Customer::class,
    'branded value object' => Email::class,
    'value object with inherited metadata' => \Tests\Mocks\ValueObjects\Inherited\AccountId::class,
    'value object in struct' => 'array{e: ' . Email::class . '}',
    'named class in list' => 'list<' . Customer::class . '>',
    'named class in union' => Customer::class . '|null',
    'named class in record' => 'array<string, ' . Customer::class . '>',
]);

test('the parser produces metadata for every position under test', function (string $type) {
    expect(containsMetadataNode(new TypeParser()->parse($type)))->toBeTrue(
        "Expected {$type} to carry metadata before optimization; the elimination assertion would be vacuous otherwise.",
    );
})->with('metadata positions');

test('the optimizer eliminates metadata from the generated code', function (string $type) {
    ['code' => $code] = optimizeSingle(new TypeParser()->parse($type));

    expect($code)->not->toContain('MetadataNode');
})->with('metadata positions');

test('the optimizer eliminates metadata from the instantiated AST', function (string $type) {
    ['node' => $node] = optimizeSingle(new TypeParser()->parse($type));

    expect(containsMetadataNode($node))->toBeFalse();
})->with('metadata positions');

test('an optimized AST parses identically to the metadata carrying one', function () {
    $node = new TypeParser()->parse("array{token: BrandedString<'tok'>, count: int}");
    ['node' => $optimized] = optimizeSingle($node);

    $executor = new Le0daniel\PhpTsBindings\Executor\SchemaExecutor();
    $data = ['token' => 'abc', 'count' => 3];

    expect($executor->parse($optimized, $data)->value)
        ->toEqual($executor->parse($node, $data)->value);
});

test('MetadataNode cannot serialize itself even outside the optimizer', function () {
    $node = new MetadataNode(new StringNode(), new NamedType('Token'), 'token');

    expect($node->exportPhpCode())->not->toContain('MetadataNode')
        ->and($node->exportPhpCode())->toBe(new StringNode()->exportPhpCode())
        ->and((string)$node)->toBe((string)new StringNode());
});

test('MetadataNode rejects being nested', function () {
    $node = new MetadataNode(new MetadataNode(new StringNode(), null, 'inner'), null, 'outer');

    expect(fn() => $node->validate())
        ->toThrow(ParserException::class, 'should not be nested');
});

test('MetadataNode rejects carrying neither a name nor a brand', function () {
    expect(fn() => new MetadataNode(new StringNode())->validate())
        ->toThrow(ParserException::class, 'meaningless');
});

test('unwrapMetadata strips the wrapper and leaves everything else alone', function () {
    $inner = new IntNode();

    expect(Nodes::unwrapMetadata(new MetadataNode($inner, null, 'tag')))->toBe($inner)
        ->and(Nodes::unwrapMetadata($inner))->toBe($inner);
});

test('unwrapMetadata keeps constraints attached, unlike getDeclaringNode', function () {
    $constrained = new Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode(
        new StringNode(),
        [new Le0daniel\PhpTsBindings\Parser\Constraints\NonEmptyString()],
    );
    $wrapped = new MetadataNode($constrained, null, 'tag');

    expect(Nodes::unwrapMetadata($wrapped))->toBe($constrained)
        ->and(Nodes::getDeclaringNode($wrapped))->toBeInstanceOf(StringNode::class);
});
