<?php

namespace Tests\Unit\Executor;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Parser\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Parser\TypeStringTokenizer;

/**
 * @template T
 * @param NodeInterface $node
 * @param Closure(NodeInterface): T $executor
 * @return T
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

function produceAst(string $typeString): NodeInterface
{
    $parser = new TypeParser(new TypeStringTokenizer());
    return $parser->parse($typeString);
}

test('parse', function () {
    $node = produceAst("string");
    $executor = new SchemaExecutor();

    $result = executeNodeOnOptimizedToo($node, fn(NodeInterface $node) => $executor->parse($node, 'my value'));
    expect($result)->toBeInstanceOf(Success::class);
    expect($result->data)->toBe('my value');
});


