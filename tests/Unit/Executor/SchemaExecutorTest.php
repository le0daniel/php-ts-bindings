<?php

namespace Tests\Unit\Executor;

use Closure;
use JsonException;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Parser\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Parser\TypeStringTokenizer;
use Stringable;

/**
 * @template T
 * @param NodeInterface $node
 * @param Closure(NodeInterface): T $executor
 * @return T
 * @throws JsonException
 */
function executeNodeOnOptimizedToo(NodeInterface $node, Closure $executor): mixed {
    $code = new ASTOptimizer()->generateOptimizedCode(['node' => $node]);

    /** @var NodeInterface $optimizedAst */
    $optimizedAst = eval("return ({$code})->get('node');");

    $normalResult = $executor($node);
    $optimizedResult = $executor($optimizedAst);

    expect($normalResult::class)->toEqual($optimizedResult::class);

    if ($normalResult instanceof Success) {
        $serializedResult = json_encode($normalResult->value, flags: JSON_THROW_ON_ERROR);
        $serializedOptimizedResult = json_encode($optimizedResult->value, flags: JSON_THROW_ON_ERROR);
        expect($serializedResult)->toEqual($serializedOptimizedResult);
    }

    return $normalResult;
};

/**
 * @throws \Throwable
 * @throws JsonException
 */
function executeParse(string $typeString, mixed $value): Success|Failure
{
    $parser = new TypeParser(new TypeStringTokenizer());
    $node = $parser->parse($typeString);
    $executor = new SchemaExecutor();
    return executeNodeOnOptimizedToo($node, fn(NodeInterface $node) => $executor->parse($node, $value));
}

/**
 * @throws \Throwable
 * @throws JsonException
 */
function executeSerialize(string $typeString, mixed $value): Success|Failure
{
    $parser = new TypeParser(new TypeStringTokenizer());
    $node = $parser->parse($typeString);
    $executor = new SchemaExecutor();
    return executeNodeOnOptimizedToo($node, fn(NodeInterface $node) => $executor->serialize($node, $value));
}

test('parse success', function (string $type, mixed $value, mixed $expected) {
    $result = executeParse($type, $value);
    expect($result)->toBeSuccess();

    if (is_object($expected)) {
        expect($result->value)->toBeInstanceOf(get_class($expected));
        expect($result->value)->toEqual($expected);
        return;
    }
    expect($result->value)->toBe($expected);
})->with([
    ['string', 'my value', 'my value'],
    ['string|null', 'my value', 'my value'],
    ['?string', 'my value', 'my value'],
    ['null|int|string', 'my value', 'my value'],
    ['null|int|string', null, null],
    ['string|int', 1, 1],
    ['array{int, string}', [1, 'my value'], [1, 'my value']],
    ['array{0: int,1: string}', [1, 'my value'], [1, 'my value']],
    ['array{id?: string, name: string}', ['id' => 'my id', 'name' => 'my name'], ['id' => 'my id', 'name' => 'my name']],
    ['array{id?: string, name: string}', ['name' => 'my name', 'other' => ''], ['name' => 'my name']],
    ['object{id?: string, name: string}', ['name' => 'my name', 'other' => ''], (object) ['name' => 'my name']],
    ['object{id?: string, name: string}|null', ['name' => 'my name', 'other' => ''], (object) ['name' => 'my name']],
    ['object{id?: string, name: string}|null', null, null],
    ['array<string, int>', ['my value' => 1], ['my value' => 1]],
]);

test('serialize success', function (string $type, mixed $value, mixed $expected) {
    $result = executeSerialize($type, $value);

    expect($result)->toBeSuccess();

    if (is_object($expected)) {
        expect($result->value)->toBeInstanceOf(get_class($expected));
        expect($result->value)->toEqual($expected);
        return;
    }
    expect($result->value)->toBe($expected);
})->with([
    ['string', 'my value', 'my value'],
    ['string', new class () implements Stringable {
        public function __toString(): string {
            return 'my value';
        }
    }, 'my value'],
    ['string|null', 'my value', 'my value'],
    ['?string', 'my value', 'my value'],
    ['null|int|string', 'my value', 'my value'],
    ['null|int|string', null, null],
    ['string|int', 1, 1],
    ['array{int, string}', [1, 'my value'], [1, 'my value']],
    ['array{0: int,1: string}', [1, 'my value'], [1, 'my value']],
    ['array{id?: string, name: string}', ['id' => 'my id', 'name' => 'my name'], (object) ['id' => 'my id', 'name' => 'my name']],
    ['array{id?: string, name: string}', ['name' => 'my name', 'other' => ''], (object) ['name' => 'my name']],
    ['object{id?: string, name: string}', ['name' => 'my name', 'other' => ''], (object) ['name' => 'my name']],
    ['object{id?: string, name: string}|null', ['name' => 'my name', 'other' => ''], (object) ['name' => 'my name']],
    ['object{id?: string, name: string}|null', null, null],
    ['array<string>', ['my value', 'my other value'], ['my value', 'my other value']],
    ['array<string, int>', ['my value' => 1], (object) ['my value' => 1]],
]);


