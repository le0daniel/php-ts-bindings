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

use Le0daniel\PhpTsBindings\CodeGen\Data\DefinitionTarget;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptDefinitionGenerator;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Parser\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\AstSorter;
use Le0daniel\PhpTsBindings\Parser\AstValidator;

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

function typescriptDefinition(NodeInterface $node, DefinitionTarget $target): string
{
    $sortedNode = AstSorter::sort($node);

    $optimizer = new ASTOptimizer();
    $optimizedCode = $optimizer->generateOptimizedCode(['node' => $sortedNode]);

    /** @var \Le0daniel\PhpTsBindings\Parser\Registry\CachedTypeRegistry $registry */
    $registry = eval("return {$optimizedCode};");

    $tsGenerator = new TypescriptDefinitionGenerator();

    foreach (DefinitionTarget::cases() as $case) {
        $expected = $tsGenerator->toDefinition($sortedNode, $case);
        $optimized = $tsGenerator->toDefinition($registry->get('node'), $case);
        expect($expected)->toEqual($optimized);
    }

    return $tsGenerator->toDefinition($sortedNode, $target);
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
