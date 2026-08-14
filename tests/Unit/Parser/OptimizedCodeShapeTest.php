<?php

declare(strict_types=1);

use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\UnknownTypeKeyException;
use Le0daniel\PhpTsBindings\Parser\Helpers\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\Helpers\Registry\CachedTypeRegistry;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

/**
 * The generated registry is required on every request, so its shape is a runtime cost, not just a
 * cosmetic choice. One match() arm per entry replaces one Closure per entry: same laziness, no
 * per-entry allocation.
 */
function generateFor(string ...$types): string
{
    $parser = new TypeParser();
    $schemas = [];
    foreach ($types as $index => $type) {
        $schemas["schema{$index}"] = $parser->parse($type);
    }

    return new ASTOptimizer()->generateOptimizedCode($schemas);
}

test('the registry is built from a single match factory, not one closure per entry', function () {
    $code = generateFor('array{a: string, b: int}', 'array{c: bool}');

    expect($code)->toContain('match (')
        ->and($code)->not->toContain('static fn(');
});

test('identifiers are short', function () {
    $code = generateFor('array{a: string, b: int, c: list<string>}');

    preg_match_all("/'(#[a-z][a-f0-9]+)'/", $code, $matches);

    expect($matches[1])->not->toBeEmpty();
    foreach (array_unique($matches[1]) as $identifier) {
        expect(strlen($identifier))->toBeLessThanOrEqual(13, "Identifier {$identifier} is too long");
    }
});

test('the generated code is loadable and resolves its schemas', function () {
    $code = generateFor('array{a: string, b: int}');

    /** @var CachedTypeRegistry $registry */
    $registry = eval("return {$code};");

    expect($registry)->toBeInstanceOf(CachedTypeRegistry::class)
        ->and((string) $registry->get('schema0'))->toBe('array{a: string, b: int}');
});

test('generation is deterministic: the same input yields byte identical output', function () {
    $type = 'array{zebra: string, alpha: list<int>, nested: array{x: bool}}';

    expect(generateFor($type))->toBe(generateFor($type));
});

test('an unknown key raises a typed exception saying the cache has to be regenerated', function () {
    $code = generateFor('array{a: string}');

    /** @var CachedTypeRegistry $registry */
    $registry = eval("return {$code};");

    expect(fn () => $registry->get('does-not-exist'))
        ->toThrow(UnknownTypeKeyException::class, 'Regenerate the optimized schema cache');
});

test('the legacy array shape is rejected rather than silently accepted', function () {
    // A cache written before identity was fixed carries merged schemas; booting it would run with
    // constraints silently dropped, so it must fail loudly instead.
    expect(fn () => new CachedTypeRegistry(['key' => static fn () => new TypeParser()->parse('string')]))
        ->toThrow(UnknownTypeKeyException::class, 'Regenerate the optimized schema cache');
});

test('resolved nodes are memoized', function () {
    $code = generateFor('array{a: string}');

    /** @var CachedTypeRegistry $registry */
    $registry = eval("return {$code};");

    expect($registry->get('schema0'))->toBe($registry->get('schema0'));
});

test('shared subtrees resolve to one shared instance', function () {
    $code = new ASTOptimizer()->generateOptimizedCode([
        'a' => new TypeParser()->parse('array{shared: string}'),
        'b' => new TypeParser()->parse('array{shared: string}'),
    ]);

    /** @var CachedTypeRegistry $registry */
    $registry = eval("return {$code};");

    expect($registry->get('a'))->toBe($registry->get('b'));
});
