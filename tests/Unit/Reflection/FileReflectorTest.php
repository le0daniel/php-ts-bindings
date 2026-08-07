<?php

declare(strict_types=1);

namespace Tests\Unit\Reflection;

use Le0daniel\PhpTsBindings\Parser\Helpers\ParsingScope;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\DateTimeNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Reflection\FileReflector;
use Le0daniel\PhpTsBindings\Utils\Namespaces;
use Tests\Unit\Reflection\Fixtures\ClassConstantBeforeDeclaration;

test('a ::class constant above the declaration is not mistaken for it', function () {
    // Discovery reflects every .php file under the discovery path, so one file shaped like this
    // used to abort discovery of the whole application.
    $reflector = new FileReflector(__DIR__.'/Fixtures/ClassConstantBeforeDeclaration.php');

    expect($reflector->getDeclaredClass()->getName())->toBe(ClassConstantBeforeDeclaration::class);
});

test('the namespace is read from the file', function () {
    $reflector = new FileReflector(__DIR__.'/Fixtures/ClassConstantBeforeDeclaration.php');

    expect($reflector->getDeclaredClass()->getNamespaceName())->toBe('Tests\\Unit\\Reflection\\Fixtures');
});

/**
 * Anything missing from this map silently becomes a name resolved against the file's own namespace,
 * which is how `use DateTimeImmutable;` used to produce Some\Namespace\DateTimeImmutable.
 */
test('every use statement shape lands in the alias map', function () {
    $reflector = new FileReflector(__DIR__.'/Fixtures/EveryUseStatementShape.php');

    expect(Namespaces::buildNamespaceAliasMap($reflector->getUsedNamespaces()))->toBe([
        // Single segment: the token is a plain T_STRING, not T_NAME_QUALIFIED.
        'arrayobject' => 'ArrayObject',
        'counted' => 'Countable',
        'datetimeimmutable' => 'DateTimeImmutable',
        // Group use: one entry per member, each honouring its own alias.
        'astoptimizer' => 'Le0daniel\PhpTsBindings\Parser\Helpers\ASTOptimizer',
        'scope' => 'Le0daniel\PhpTsBindings\Parser\Helpers\ParsingScope',
        'typeparser' => 'Le0daniel\PhpTsBindings\Parser\TypeParser',
        'ns' => 'Le0daniel\PhpTsBindings\Utils\Namespaces',
    ]);
});

test('trait uses and closure captures are not imports', function () {
    $reflector = new FileReflector(__DIR__.'/Fixtures/EveryUseStatementShape.php');
    $map = Namespaces::buildNamespaceAliasMap($reflector->getUsedNamespaces());

    // `use SomeTrait;` inside the class body and `function () use ($offset)` both start with T_USE.
    // A scan that reads the closure capture runs on to the next `;` and picks a qualified name out
    // of the body, so NotAnImport is the canary for that.
    expect($map)->not->toHaveKey('sometrait')
        ->and($map)->not->toHaveKey('notanimport')
        ->and($map)->not->toHaveKey('offset')
        // `use function` and `use const` are not type imports either.
        ->and($map)->not->toHaveKey('array_map')
        ->and($map)->not->toHaveKey('php_eol');
});

test('a single segment import resolves instead of being prefixed with the namespace', function () {
    $scope = ParsingScope::fromFilePath(__DIR__.'/Fixtures/EveryUseStatementShape.php');

    expect($scope->toFullyQualifiedClassName('DateTimeImmutable'))->toBe('DateTimeImmutable')
        ->and($scope->toFullyQualifiedClassName('Counted'))->toBe('Countable')
        ->and($scope->toFullyQualifiedClassName('Scope'))
        ->toBe('Le0daniel\PhpTsBindings\Parser\Helpers\ParsingScope')
        // Not imported, so it stays relative to the file.
        ->and($scope->toFullyQualifiedClassName('NotAnImport'))
        ->toBe('Tests\Unit\Reflection\Fixtures\NotAnImport');
});

test('a single segment import carries a date time all the way through the parser', function () {
    // DateTimeConsumer used to compensate for the dropped import by testing class_exists() against
    // the raw token; with the import map complete it resolves through the scope like anything else.
    $scope = ParsingScope::fromFilePath(__DIR__.'/Fixtures/EveryUseStatementShape.php');

    expect(new TypeParser()->parse('DateTimeImmutable', $scope))->toBeInstanceOf(DateTimeNode::class);
});
