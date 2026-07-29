<?php declare(strict_types=1);

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Parser\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\Registry\CachedTypeRegistry;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

/**
 * The optimizer pools every operation's schemas into one registry, so two schemas that hash the
 * same silently share one node tree. These tests assert parity against the un-optimized AST for
 * schema pairs that are structurally similar but behaviourally different.
 */

/**
 * Optimizes several schemas together — the production configuration — and returns the registry.
 *
 * @param array<string, NodeInterface|string> $schemas
 */
function optimizePooled(array $schemas): CachedTypeRegistry
{
    $parser = new TypeParser();
    $nodes = array_map(
        static fn(NodeInterface|string $schema) => is_string($schema) ? $parser->parse($schema) : $schema,
        $schemas,
    );

    $code = new ASTOptimizer()->generateOptimizedCode($nodes);

    /** @var CachedTypeRegistry $registry */
    $registry = eval("return {$code};");
    return $registry;
}

/**
 * Asserts the optimized schema behaves exactly like the freshly parsed one, in BOTH pool orders —
 * a collision's direction flips with iteration order, so one order alone can hide the bug.
 *
 * @param array<string, string> $schemas
 * @param array<string, list<mixed>> $probes schema key => values to parse
 */
function assertPooledParity(array $schemas, array $probes): void
{
    $parser = new TypeParser();
    $executor = new SchemaExecutor();

    foreach ([$schemas, array_reverse($schemas, true)] as $ordered) {
        $registry = optimizePooled($ordered);

        foreach ($probes as $key => $values) {
            foreach ($values as $value) {
                $raw = $executor->parse($parser->parse($schemas[$key]), $value);
                $optimized = $executor->parse($registry->get($key), $value);

                $encoded = json_encode($value, JSON_THROW_ON_ERROR);
                expect($optimized::class)->toBe(
                    $raw::class,
                    "Schema '{$key}' with {$encoded} diverged (pool order: " . implode(',', array_keys($ordered)) . ')',
                );

                if ($raw instanceof Success) {
                    expect(json_encode($optimized->value, JSON_THROW_ON_ERROR))
                        ->toBe(json_encode($raw->value, JSON_THROW_ON_ERROR));
                }
            }
        }
    }
}

test('C1: a constrained schema keeps its constraints when pooled with an unconstrained twin', function () {
    assertPooledParity(
        [
            'unconstrained' => 'array{email: string}',
            'constrained' => 'array{email: non-empty-string}',
        ],
        [
            'unconstrained' => [['email' => ''], ['email' => 'a@b.c']],
            'constrained' => [['email' => ''], ['email' => 'a@b.c']],
        ],
    );
});

test('C1: constraints on nested structs survive pooling', function () {
    assertPooledParity(
        [
            'loose' => 'array{user: array{name: string}}',
            'strict' => 'array{user: array{name: non-empty-string}}',
        ],
        [
            'loose' => [['user' => ['name' => '']]],
            'strict' => [['user' => ['name' => '']]],
        ],
    );
});

test('C2: int and float literals do not collapse into one another', function () {
    $intNode = new LiteralNode(LiteralType::INT, 1);
    $floatNode = new LiteralNode(LiteralType::FLOAT, 1.0);

    expect($intNode->exportPhpCode())->not->toBe($floatNode->exportPhpCode());

    $registry = optimizePooled(['int' => $intNode, 'float' => $floatNode]);

    expect($registry->get('int'))->toBeInstanceOf(LiteralNode::class)
        ->and($registry->get('int')->type)->toBe(LiteralType::INT)
        ->and($registry->get('float')->type)->toBe(LiteralType::FLOAT);
});

test('C2: a float literal schema pooled with an int literal twin still rejects the int', function () {
    $executor = new SchemaExecutor();
    $registry = optimizePooled([
        'int' => new LiteralNode(LiteralType::INT, 1),
        'float' => new LiteralNode(LiteralType::FLOAT, 1.0),
    ]);

    expect($executor->parse($registry->get('float'), 1.0))->toBeInstanceOf(Success::class)
        ->and($executor->parse($registry->get('int'), 1))->toBeInstanceOf(Success::class)
        ->and($executor->parse($registry->get('float'), 1))->toBeInstanceOf(Failure::class);
});

test('C3: unions differing only in discriminator do not share an entry', function () {
    $discriminated = new TypeParser()->parse("array{kind: 'a', v: string}|array{kind: 'b', v: int}");
    $plain = new Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode($discriminated->types);

    expect($discriminated->exportPhpCode())->not->toBe($plain->exportPhpCode());

    $registry = optimizePooled(['discriminated' => $discriminated, 'plain' => $plain]);

    expect($registry->get('discriminated')->discriminator)->toBe('kind')
        ->and($registry->get('plain')->discriminator)->toBeNull();
});

test('identical schemas still share a single interned entry', function () {
    $code = new ASTOptimizer()->generateOptimizedCode([
        'a' => new TypeParser()->parse('array{name: string}'),
        'b' => new TypeParser()->parse('array{name: string}'),
    ]);

    // Prefix-agnostic: matches both the '#struct_<sha1>' and the shortened '#s<hash>' form.
    expect(preg_match_all('/\'#s(?:truct_)?[a-f0-9]+\' =>/', $code))
        ->toBe(1, 'Two identical schemas must intern to exactly one struct entry.');
});

test('the collision guard fires when identifiers are truncated too far', function () {
    $optimizer = new ASTOptimizer(idLength: 1);

    // 16 distinct property names cannot fit in a single hex character without colliding.
    $schemas = [];
    foreach (range('a', 'z') as $letter) {
        $schemas[$letter] = new TypeParser()->parse("array{{$letter}: string}");
    }

    expect(fn() => $optimizer->generateOptimizedCode($schemas))
        ->toThrow(RuntimeException::class, 'collision');
});

test('node keys starting with a hash are rejected so they cannot shadow interned ids', function () {
    expect(fn() => new ASTOptimizer()->generateOptimizedCode([
        '#leaf_evil' => new TypeParser()->parse('string'),
    ]))->toThrow(RuntimeException::class, 'MUST not start with a # character');
});
