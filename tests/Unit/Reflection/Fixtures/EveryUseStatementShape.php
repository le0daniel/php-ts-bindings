<?php

declare(strict_types=1);

namespace Tests\Unit\Reflection\Fixtures;

use ArrayObject;
use Countable as Counted;
use DateTimeImmutable;
use Le0daniel\PhpTsBindings\Parser\Helpers\{ASTOptimizer, ParsingScope as Scope};
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Utils\Namespaces as Ns;

use function array_map;

use const PHP_EOL;

/**
 * Every shape a `use` statement can take, plus the two constructs that merely look like one: a trait
 * `use` inside the class body and a closure capture. Neither is an import and neither may end up in
 * the map - the closure body mentions a qualified name precisely to catch a scan that runs past it.
 *
 * Every import is referenced somewhere below so that no formatter decides to tidy one away; the
 * shapes are the fixture.
 */
final class EveryUseStatementShape
{
    use SomeTrait;

    /** @var ArrayObject<int, string> */
    public ArrayObject $items;

    public DateTimeImmutable $createdAt;

    public function run(int $offset): callable
    {
        $scope = new Scope();
        $names = array_map(
            fn (string $name): string => Ns::toFullyQualifiedClassName($name, null, []),
            [Counted::class, ASTOptimizer::class, TypeParser::class],
        );

        return function () use ($offset, $scope, $names): string {
            // Deliberately a T_NAME_QUALIFIED token: a scan that reads the closure capture as a use
            // statement runs on to the next `;` and picks this up as an import.
            return Nested\NotAnImport::class.PHP_EOL.$offset.implode('', $names).$scope::class;
        };
    }
}
