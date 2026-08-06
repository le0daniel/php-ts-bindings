<?php

declare(strict_types=1);

use Le0daniel\PhpTsBindings\CodeGen\Utils\ErrorTypescript;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Tests\Mocks\Errors\ErrorOperations;
use Tests\Mocks\Errors\RecordMissingException;
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

// Only the two categories that have something to add carry details; for the rest the category is
// the whole answer and the server omits the key.
const INVALID_INPUT_BRANCH = '{code: 422, type: "INVALID_INPUT", details: {fields: Record<string, string[]>}}';
const UNAUTHENTICATED_BRANCH = '{code: 401, type: "AUTHENTICATION_ERROR"}';
const UNAUTHORIZED_BRANCH = '{code: 403, type: "AUTHORIZATION_ERROR"}';
const NOT_FOUND_BRANCH = '{code: 404, type: "NOT_FOUND"}';
const INTERNAL_BRANCH = '{code: 500, type: "INTERNAL_ERROR"}';

test('an unconfigured server only emits the branches it can actually produce', function () {
    $union = ErrorTypescript::forOperation(new ServerConfiguration(), typescriptDefinition('declaresNothing'));

    expect($union)->toBe(implode('|', [
        INVALID_INPUT_BRANCH,
        NOT_FOUND_BRANCH,
        INTERNAL_BRANCH,
    ]));
});

test('the authentication branch appears once unauthenticated exceptions are configured', function () {
    $configuration = new ServerConfiguration()->withExceptions(unauthenticated: [RecordMissingException::class]);

    $union = ErrorTypescript::forOperation($configuration, typescriptDefinition('declaresNothing'));

    expect($union)->toBe(implode('|', [
        INVALID_INPUT_BRANCH,
        UNAUTHENTICATED_BRANCH,
        NOT_FOUND_BRANCH,
        INTERNAL_BRANCH,
    ]));
});

test('the authorization branch appears once unauthorized exceptions are configured', function () {
    $configuration = new ServerConfiguration()->withExceptions(unauthorized: [RecordMissingException::class]);

    $union = ErrorTypescript::forOperation($configuration, typescriptDefinition('declaresNothing'));

    expect($union)->toBe(implode('|', [
        INVALID_INPUT_BRANCH,
        UNAUTHORIZED_BRANCH,
        NOT_FOUND_BRANCH,
        INTERNAL_BRANCH,
    ]));
});

test('the domain branch lists every exposed exception the operation declares, just before the catch all', function () {
    $union = ErrorTypescript::forOperation(new ServerConfiguration(), typescriptDefinition('declaresThrows', [ThrowingMiddleware::class]));

    expect($union)->toBe(implode('|', [
        INVALID_INPUT_BRANCH,
        NOT_FOUND_BRANCH,
        '{code: 400, type: "DOMAIN_ERROR", details: {type: "domain_failure"}|{type: "middleware_failure"}}',
        INTERNAL_BRANCH,
    ]));
});

test('the domain branch is named by the as of a Throws, not by the ExposeAs it overrides', function () {
    $union = ErrorTypescript::forOperation(new ServerConfiguration(), typescriptDefinition('declaresRenamedThrows'));

    expect($union)->toContain('{code: 400, type: "DOMAIN_ERROR", details: {type: "renamed_failure"}|{type: "overridden_failure"}}')
        ->and($union)->not->toContain('domain_failure');
});

test('an exception declared by both the operation and a middleware appears once', function () {
    $union = ErrorTypescript::forOperation(
        new ServerConfiguration(),
        typescriptDefinition('declaresRenamedThrows', [RenamingMiddleware::class]),
    );

    expect($union)->toContain('{code: 400, type: "DOMAIN_ERROR", details: {type: "renamed_failure"}|{type: "overridden_failure"}|{type: "renamed_middleware_failure"}}')
        ->and($union)->not->toContain('middleware_name');
});

test('an operation declaring nothing exposable emits no domain branch', function () {
    $union = ErrorTypescript::forOperation(new ServerConfiguration(), typescriptDefinition('declaresNothing'));

    expect($union)->not->toContain('DOMAIN_ERROR');
});

test('an operation whose only #[Throws] lacks ExposeAs emits no domain branch', function () {
    // declaresThrows declares UnexposedException alongside ExposedDomainException, so the branch
    // must list only the exposed one.
    $union = ErrorTypescript::forOperation(new ServerConfiguration(), typescriptDefinition('declaresThrows'));

    expect($union)->toContain('{code: 400, type: "DOMAIN_ERROR", details: {type: "domain_failure"}}')
        ->and($union)->not->toContain('UnexposedException');
});
