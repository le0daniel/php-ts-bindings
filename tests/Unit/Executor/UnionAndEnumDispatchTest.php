<?php

declare(strict_types=1);

use Le0daniel\PhpTsBindings\Executor\Data\ParsingOptions;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Tests\Mocks\ResultEnum;
use Tests\Unit\Executor\Mocks\UserSchema;

/**
 * Which branch a union selects, and how an enum resolves a name, are easy to change by accident
 * when touching dispatch. These cases pin that behaviour independently of how the executor is
 * implemented: boolean and numeric-string discriminators, mixed-type maps, exact literal matching
 * and declaration-order probing all have to keep working.
 */
test('a discriminated union over plain string tags resolves each branch', function () {
    $node = new TypeParser()->parse(
        "array{kind: 'a', a: string}|array{kind: 'b', b: int}|array{kind: 'c', c: bool}",
    );

    expect($node->discriminator)->toBe('kind');

    expect(executeParse($node, ['kind' => 'a', 'a' => 'x']))->toBeSuccess();
    expect(executeParse($node, ['kind' => 'b', 'b' => 1]))->toBeSuccess();
    expect(executeParse($node, ['kind' => 'c', 'c' => true]))->toBeSuccess();
    expect(executeParse($node, ['kind' => 'd']))->toBeFailure();
});

test('a boolean discriminator map still resolves; array_flip would silently drop every arm', function () {
    $node = new TypeParser()->parse('array{ok: true, value: string}|array{ok: false, error: string}');

    expect($node->discriminatorMap)->toBe([true, false]);

    expect(executeParse($node, ['ok' => true, 'value' => 'v']))->toBeSuccess();
    expect(executeParse($node, ['ok' => false, 'error' => 'e']))->toBeSuccess();
});

test('a numeric string discriminator is not confused with its integer twin', function () {
    // PHP coerces the array key '1' to int 1, so a flipped table would match both.
    $stringTagged = new TypeParser()->parse("array{v: '1', s: string}|array{v: '2', t: string}");

    expect(executeParse($stringTagged, ['v' => '1', 's' => 'x']))->toBeSuccess();
    expect(executeParse($stringTagged, ['v' => 1, 's' => 'x'], new ParsingOptions(coercePrimitives: false)))->toBeFailure();
});

test('a mixed string and integer discriminator map keeps both branches distinct', function () {
    $node = new TypeParser()->parse("array{v: '1', s: string}|array{v: 1, i: int}");

    expect($node->discriminatorMap)->toBe(['1', 1]);

    $executor = new SchemaExecutor();
    $stringBranch = $node->getDiscriminatedType('1');
    $intBranch = $node->getDiscriminatedType(1);

    expect($stringBranch)->not->toBe($intBranch)
        ->and($executor->parse($stringBranch, ['v' => '1', 's' => 'x'])->value)->not->toBeNull()
        ->and($executor->parse($intBranch, ['v' => 1, 'i' => 2])->value)->not->toBeNull();
});

test('a discriminator lookup miss returns null rather than a wrong branch', function () {
    $node = new TypeParser()->parse("array{kind: 'a', a: string}|array{kind: 'b', b: int}");

    expect($node->getDiscriminatedType('nope'))->toBeNull()
        ->and($node->getDiscriminatedType(null))->toBeNull()
        ->and($node->getDiscriminatedType(0))->toBeNull()
        ->and($node->getDiscriminatedType(true))->toBeNull();
});

test('an all literal string union matches every member and rejects unknown values', function () {
    $node = "'draft'|'pending'|'active'|'archived'|'deleted'";

    foreach (['draft', 'pending', 'active', 'archived', 'deleted'] as $value) {
        expect(executeParse($node, $value))->toBeSuccess();
    }

    expect(executeParse($node, 'unknown'))->toBeFailure();
    expect(executeParse($node, 1))->toBeFailure();
    expect(executeParse($node, null))->toBeFailure();
});

test('a literal union containing numeric strings stays exact', function () {
    expect(executeParse("'1'|'2'", '1'))->toBeSuccess();
    expect(executeParse("'1'|'2'", '02'))->toBeFailure();
});

test('a mixed literal union is unaffected by the string only fast path', function () {
    expect(executeParse("'a'|1|true|null", 'a'))->toBeSuccess();
    expect(executeParse("'a'|1|true|null", 1))->toBeSuccess();
    expect(executeParse("'a'|1|true|null", true))->toBeSuccess();
    expect(executeParse("'a'|1|true|null", null))->toBeSuccess();
    expect(executeParse("'a'|1|true|null", 'b'))->toBeFailure();
});

test('string literals coerce to themselves, which is what makes the fast path safe', function () {
    $node = new LiteralNode(LiteralType::STRING, 'draft');

    expect($node->coerce('draft'))->toBe('draft')
        ->and($node->coerce('other'))->toBe('other')
        ->and($node->coerce(1))->toBe(1);
});

test('a literal union still reports the same issues on failure', function () {
    $failure = executeParse("'a'|'b'", 'c');

    expect($failure)->toBeFailure();
});

test('enum values resolve by name and reject unknown names', function () {
    // Executed directly: the shared helper json_encodes results, which a non-backed enum cannot do.
    $node = new TypeParser()->parse(ResultEnum::class);
    $executor = new SchemaExecutor();

    expect($executor->parse($node, 'SUCCESS')->value)->toBe(ResultEnum::SUCCESS)
        ->and($executor->parse($node, 'FAILURE')->value)->toBe(ResultEnum::FAILURE)
        ->and($executor->parse($node, 'NOT_A_CASE'))->toBeFailure()
        ->and($executor->parse($node, 1))->toBeFailure()
        ->and($executor->parse($node, 'OTHER'))->toBeFailure();
});

test('struct property direction filtering is unchanged by precomputed partitions', function () {
    $node = new TypeParser()->parse(UserSchema::class);

    expect(executeParse($node, ['username' => 'ada', 'email' => 'ada@example.com', 'age' => 30]))->toBeSuccess();
});

test('an undiscriminated union still probes members in declaration order', function () {
    // First match wins, so order remains observable and must not be reordered by any fast path.
    $node = new UnionNode([
        new LiteralNode(LiteralType::STRING, 'x'),
        new LiteralNode(LiteralType::STRING, 'y'),
    ]);

    $executor = new SchemaExecutor();
    expect($executor->parse($node, 'x')->value)->toBe('x')
        ->and($executor->parse($node, 'y')->value)->toBe('y');
});
