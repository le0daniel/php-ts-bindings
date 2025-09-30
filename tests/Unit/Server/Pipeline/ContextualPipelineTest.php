<?php

namespace Tests\Unit\Server\Pipeline;

use Closure;
use Exception;
use Le0daniel\PhpTsBindings\Server\Pipeline\ContextualPipeline;
use RuntimeException;

test('Test pipeline ordering', function () {

    $pipeline = new ContextualPipeline([
        new class {
            public function handle(string $input, Closure $next, string $context)
            {
                $result = $next($input, $context);
                return "First<{$result}>";
            }
        },
        new class {
            public function handle(string $input, Closure $next, string $context)
            {
                $result = $next($input, $context);
                return "Second<{$result}>";
            }
        }
    ])->then(function (string $input, string $context) {
        return "Middle<{$context}>";
    });

    $result = $pipeline->execute('input', 'context');
    expect($result)->toBe('First<Second<Middle<context>>>');
});

test('Error handling on throw or return of throwable', function () {
    $pipeline = new ContextualPipeline([
        new class {
            public function handle(string $input, Closure $next, string $context)
            {
                $result = $next($input, $context);
                return new RuntimeException("first<{$result}>");
            }
        },
        new class {
            public function handle(string $input, Closure $next, string $context)
            {
                throw new Exception('second<' . $next($input) . '>');
            }
        }
    ])->then(function (string $input, string $context) {
        return "Middle<{$context}>";
    })->catchErrorsWith(function (\Throwable $e) {
        return $e->getMessage();
    });

    $result = $pipeline->execute('input', 'context');
    expect($result)->toBe('first<second<Middle<context>>>');
});

test('Test pipeline with array push', function () {

    $pipeline = new ContextualPipeline([
        new class {
            public function handle(array $input, Closure $next, string $context)
            {
                $input[] = "Enter first";
                $result = $next($input, $context);
                $result[] = "Exit first";
                return $result;
            }
        },
        new class {
            public function handle(array $input, Closure $next, string $context)
            {
                $input[] = "Enter second";
                $result = $next($input, $context);
                $result[] = "Exit second";
                return $result;
            }
        }
    ])->then(function (array $input, string $context) {
        $input[] = "Middle<{$context}>";
        return $input;
    });

    $result = $pipeline->execute([], 'context');
    expect($result)->toBe([
        "Enter first",
        "Enter second",
        "Middle<context>",
        "Exit second",
        "Exit first",
    ]);
});