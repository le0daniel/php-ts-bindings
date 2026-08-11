<?php

declare(strict_types=1);

use Le0daniel\PhpTsBindings\CodeGen\Utils\ErrorTypescript;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Tests\Mocks\Errors\ErrorOperations;
use Tests\Mocks\Errors\RenamingMiddleware;
use Tests\Mocks\Errors\ThrowingMiddleware;

/**
 * @param  list<class-string>  $middleware
 */
function typescriptDefinition(string $methodName = 'declaresThrows', array $middleware = []): Definition
{
    return new Definition(
        OperationType::COMMAND,
        ErrorOperations::class,
        $methodName,
        'test',
        'errors',
        // @phpstan-ignore-next-line -- tests intentionally pass unresolvable class names.
        $middleware,
    );
}

test('the domain types list every exposed exception the operation declares', function () {
    $domainTypes = ErrorTypescript::domainTypesFor(
        typescriptDefinition('declaresThrows', [ThrowingMiddleware::class]),
    );

    expect($domainTypes)->toBe('"domain_failure"|"middleware_failure"');
});

test('a domain type is named by the name of a Throws, not by the ExposeAs it overrides', function () {
    $domainTypes = ErrorTypescript::domainTypesFor(typescriptDefinition('declaresRenamedThrows'));

    expect($domainTypes)->toBe('"renamed_failure"|"overridden_failure"')
        ->and($domainTypes)->not->toContain('domain_failure');
});

test('each scope contributes its own name for a shared exception, and the union carries both', function () {
    // At runtime the name depends on which scope threw: the operation throwing
    // ExposedDomainException answers overridden_failure, the middleware throwing the same class
    // answers middleware_name. Both are reachable, so both belong in the union.
    $domainTypes = ErrorTypescript::domainTypesFor(
        typescriptDefinition('declaresRenamedThrows', [RenamingMiddleware::class]),
    );

    expect($domainTypes)->toBe('"renamed_failure"|"overridden_failure"|"renamed_middleware_failure"|"middleware_name"|"middleware_named_it"');
});

/**
 * `never` is not an absence the caller has to handle - it is what makes the 400 branch vanish from
 * that operation's Failure, so the erasure and the emptiness are the same fact.
 */
test('an operation declaring nothing exposable exposes never', function () {
    expect(ErrorTypescript::domainTypesFor(typescriptDefinition('declaresNothing')))
        ->toBe('never');
});

test('a #[Throws] lacking ExposeAs contributes no name', function () {
    // declaresThrows declares UnexposedException alongside ExposedDomainException, so only the
    // exposed one may be listed.
    $domainTypes = ErrorTypescript::domainTypesFor(typescriptDefinition('declaresThrows'));

    expect($domainTypes)->toBe('"domain_failure"')
        ->and($domainTypes)->not->toContain('UnexposedException');
});
