<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\ParsingOptions;
use Le0daniel\PhpTsBindings\Executor\Data\SerializationOptions;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Parser\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\AstSorter;
use Le0daniel\PhpTsBindings\Parser\AstValidator;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Typescript\Data\IO;
use Le0daniel\PhpTsBindings\Typescript\Data\Options;
use Le0daniel\PhpTsBindings\Typescript\Data\TypeScript;
use Le0daniel\PhpTsBindings\Typescript\TypescriptGenerator;

pest()->extend(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toBeSuccess', function () {
    /** @var Failure $value */
    $value = $this->value;

    return $this->toBeInstanceOf(Success::class, implode('', [
        "Failed asserting that result is success with: ",
        $value instanceof Failure ? $value->issues->serializeToCompleteString() : 'null'
    ]));
});

expect()->extend('toBeFailure', function (?string $message = null) {
    /** @var Failure $value */
    $value = $this->value;

    return $this->toBeInstanceOf(Failure::class)
        ->when(!is_null($message), function () use ($value, $message) {
            if (array_any($value->issues->allFlat(), fn($issue) => $issue->messageOrLocalizationKey === $message)) {
                expect(true)->toBeTrue();
                return;
            }

            $messages = array_map(fn(Issue $issue) => $issue->messageOrLocalizationKey, $value->issues->allFlat());

            expect(false)->toBeTrue(
                "Failed asserting that result is failure with message: {$message}. Got: " . implode(', ', $messages)
            );
        });
});

expect()->extend('toBeFailureAt', function (string $path, ?string $message = null) {
    /** @var Failure $value */
    $value = $this->value;

    return $this->toBeFailure()
        ->when(is_string($message), function () use ($value, $message, $path) {
            $issues = $value->issues->at($path);
            if (array_any($issues, fn($issue) => $issue->messageOrLocalizationKey === $message)) {
                expect(true)->toBeTrue();
                return;
            }

            $messages = array_map(fn(Issue $issue) => $issue->messageOrLocalizationKey, $issues);

            expect(false)->toBeTrue(
                "Failed asserting that result is failure with message: {$message}. Got: " . implode(', ', $messages)
            );
        })
        ->and(count($value->issues->at($path)) >= 1)
        ->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function compareToOptimizedAst(NodeInterface $node) {
    $sortedNode = AstSorter::sort($node);
    $optimizer = new ASTOptimizer();
    $optimizedCode = $optimizer->generateOptimizedCode(['node' => $sortedNode]);

    /** @var \Le0daniel\PhpTsBindings\Parser\Registry\CachedTypeRegistry $registry */
    $registry = eval("return {$optimizedCode};");

    expect(
        (string) $registry->get('node')
    )->toEqual((string) $sortedNode);
}

/**
 * Generates TypeScript for a node and asserts the optimized AST generates exactly the same thing.
 *
 * Only the requested direction is checked: a schema can legitimately be unrepresentable one way
 * round, and generating the other way would then throw instead of asserting.
 *
 * The parity check runs with brands ignored. Brands are code generation metadata with no runtime
 * impact, so BuiltInNode and ValueObjectNode deliberately leave them out of exportPhpCode() — an
 * optimized AST genuinely knows less about brands than the one the parser produced, and comparing
 * them branded would assert something the optimizer never promised.
 */
function typescriptFor(NodeInterface $node, IO $io, Options $options = new Options()): TypeScript
{
    $sortedNode = AstSorter::sort($node);
    $optimizer = new ASTOptimizer();
    $optimizedCode = $optimizer->generateOptimizedCode(['node' => $sortedNode]);

    /** @var \Le0daniel\PhpTsBindings\Parser\Registry\CachedTypeRegistry $registry */
    $registry = eval("return {$optimizedCode};");

    $generator = new TypescriptGenerator();
    $unbranded = new Options(pretty: $options->pretty, ignoreBrandedTypes: true);

    expect($generator->toTypescript($registry->get('node'), $io, $unbranded)->type)
        ->toEqual($generator->toTypescript($sortedNode, $io, $unbranded)->type);

    return $generator->toTypescript($sortedNode, $io, $options);
}

function executeParse(NodeInterface|string $node, mixed $data, ParsingOptions $options = new ParsingOptions()): Success|Failure
{
    $node = AstSorter::sort(
        is_string($node) ? new TypeParser()->parse($node) : $node,
    );
    $optimizer = new ASTOptimizer();
    $optimizedCode = $optimizer->generateOptimizedCode(['node' => $node]);

    /** @var \Le0daniel\PhpTsBindings\Parser\Registry\CachedTypeRegistry $registry */
    $registry = eval("return {$optimizedCode};");
    $optimizedAst = $registry->get('node');

    $executor = new SchemaExecutor();
    $normalResult = $executor->parse($node, $data, $options);
    $optimizedResult = $executor->parse($optimizedAst, $data, $options);

    expect($normalResult::class)->toEqual($optimizedResult::class);
    AstValidator::validate($node);
    AstValidator::validate($optimizedAst);

    if ($normalResult instanceof Success) {
        $serializedResult = json_encode($normalResult->value, JSON_THROW_ON_ERROR);
        $serializedOptimizedResult = json_encode($optimizedResult->value, JSON_THROW_ON_ERROR);
        expect($serializedResult)->toEqual($serializedOptimizedResult, "Optimized AST should be equal to the normal AST.");
        return $normalResult;
    }

    $serializedResult = json_encode($normalResult->issues->serializeToFieldsArray(), JSON_THROW_ON_ERROR);
    $serializedOptimizedResult = json_encode($optimizedResult->issues->serializeToFieldsArray(), JSON_THROW_ON_ERROR);
    expect($serializedResult)->toEqual($serializedOptimizedResult, "Optimized AST should be equal to the normal AST.");
    return $normalResult;
}

function executeSerialize(NodeInterface|string $node, mixed $data, SerializationOptions $options = new SerializationOptions()): Success|Failure
{
    $node = AstSorter::sort(
        is_string($node) ? new TypeParser()->parse($node) : $node,
    );
    $optimizer = new ASTOptimizer();
    $optimizedCode = $optimizer->generateOptimizedCode(['node' => $node]);

    /** @var \Le0daniel\PhpTsBindings\Parser\Registry\CachedTypeRegistry $registry */
    $registry = eval("return {$optimizedCode};");
    $optimizedAst = $registry->get('node');

    $executor = new SchemaExecutor();
    $normalResult = $executor->serialize($node, $data, $options);
    $optimizedResult = $executor->serialize($optimizedAst, $data, $options);

    expect($normalResult::class)->toEqual($optimizedResult::class);
    AstValidator::validate($node);
    AstValidator::validate($optimizedAst);

    if ($normalResult instanceof Success) {
        $serializedResult = json_encode($normalResult->value, JSON_THROW_ON_ERROR);
        $serializedOptimizedResult = json_encode($optimizedResult->value, JSON_THROW_ON_ERROR);
        expect($serializedResult)->toEqual($serializedOptimizedResult, "Optimized AST should be equal to the normal AST.");
        return $normalResult;
    }

    $serializedResult = json_encode($normalResult->issues->serializeToFieldsArray(), JSON_THROW_ON_ERROR);
    $serializedOptimizedResult = json_encode($optimizedResult->issues->serializeToFieldsArray(), JSON_THROW_ON_ERROR);
    expect($serializedResult)->toEqual($serializedOptimizedResult, "Optimized AST should be equal to the normal AST.");
    return $normalResult;
}

function validateAst(NodeInterface $node): void
{
    $optimizer = new ASTOptimizer();
    $optimizedCode = $optimizer->generateOptimizedCode(['node' => $node]);

    /** @var \Le0daniel\PhpTsBindings\Parser\Registry\CachedTypeRegistry $registry */
    $registry = eval("return {$optimizedCode};");
    $optimizedAst = $registry->get('node');

    AstValidator::validate($node);
    AstValidator::validate($optimizedAst);
    expect(true)->toBeTrue();
}

function something()
{
    // ..
}
