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

// Every branch is declared once in the generated types file, and Failure is the union of the ones
// this server can produce. Only the domain branch varies per operation, which is why it is the only
// envelope that takes a type argument - and the only thing an operation contributes to its own
// error type.
const INVALID_INPUT_ENVELOPE = 'InvalidInputError';
const UNAUTHENTICATED_ENVELOPE = 'AuthenticationError';
const UNAUTHORIZED_ENVELOPE = 'AuthorizationError';
const NOT_FOUND_ENVELOPE = 'NotFoundError';
const DOMAIN_ENVELOPE = 'DomainError<TDomainType>';
const INTERNAL_ENVELOPE = 'InternalError';
const CLIENT_ENVELOPE = 'ClientError';

/* What Failure is, per server. */

test('an unconfigured server only unions the branches it can actually produce', function () {
    expect(ErrorTypescript::failureUnion(new ServerConfiguration()))->toBe(implode('|', [
        INVALID_INPUT_ENVELOPE,
        NOT_FOUND_ENVELOPE,
        DOMAIN_ENVELOPE,
        INTERNAL_ENVELOPE,
        CLIENT_ENVELOPE,
    ]));
});

test('the authentication branch appears once unauthenticated exceptions are configured', function () {
    $configuration = new ServerConfiguration()->withExceptions(unauthenticated: [RecordMissingException::class]);

    expect(ErrorTypescript::failureUnion($configuration))->toBe(implode('|', [
        INVALID_INPUT_ENVELOPE,
        UNAUTHENTICATED_ENVELOPE,
        NOT_FOUND_ENVELOPE,
        DOMAIN_ENVELOPE,
        INTERNAL_ENVELOPE,
        CLIENT_ENVELOPE,
    ]));
});

test('the authorization branch appears once unauthorized exceptions are configured', function () {
    $configuration = new ServerConfiguration()->withExceptions(unauthorized: [RecordMissingException::class]);

    expect(ErrorTypescript::failureUnion($configuration))->toBe(implode('|', [
        INVALID_INPUT_ENVELOPE,
        UNAUTHORIZED_ENVELOPE,
        NOT_FOUND_ENVELOPE,
        DOMAIN_ENVELOPE,
        INTERNAL_ENVELOPE,
        CLIENT_ENVELOPE,
    ]));
});

/**
 * Unlike the auth branches, this one is not gated on anything the server was configured with: an
 * operation exposing nothing instantiates it with `never`, and the declaration erases it there. A
 * second gate here would only mean saying `never` twice.
 */
test('the domain branch is always in the union, carrying the parameter Failure declares', function () {
    expect(ErrorTypescript::failureUnion(new ServerConfiguration()))
        ->toContain('DomainError<'.ErrorTypescript::DOMAIN_TYPE_PARAMETER.'>');
});

/**
 * The one branch no server ever sends: the request never got there. It is appended here rather than
 * by a caller so the whole union has a single owner, and so what a client can hand back is pinned by
 * the same tests as what the server can.
 */
test('the client envelope closes the union, whatever the server is configured with', function (ServerConfiguration $configuration) {
    $union = ErrorTypescript::failureUnion($configuration);

    expect($union)->toEndWith('|'.CLIENT_ENVELOPE)
        ->and(substr_count($union, CLIENT_ENVELOPE))->toBe(1);
})->with([
    'nothing configured' => [new ServerConfiguration()],
    'auth configured' => [
        new ServerConfiguration()->withExceptions(unauthenticated: [RecordMissingException::class]),
    ],
    'both configured' => [
        new ServerConfiguration()->withExceptions(
            unauthenticated: [RecordMissingException::class],
            unauthorized: [RecordMissingException::class],
        ),
    ],
]);

test('every name the union references is a name the catalogue declares', function () {
    $referenced = explode('|', ErrorTypescript::failureUnion(
        new ServerConfiguration()->withExceptions(
            unauthenticated: [RecordMissingException::class],
            unauthorized: [RecordMissingException::class],
        ),
    ));

    foreach ($referenced as $reference) {
        expect(ErrorTypescript::envelopeNames())->toContain(strtok($reference, '<'));
    }
});

/* What an operation contributes to it. */

test('the domain types list every exposed exception the operation declares', function () {
    $domainTypes = ErrorTypescript::domainTypesFor(
        new ServerConfiguration(),
        typescriptDefinition('declaresThrows', [ThrowingMiddleware::class]),
    );

    expect($domainTypes)->toBe('"domain_failure"|"middleware_failure"');
});

test('a domain type is named by the as of a Throws, not by the ExposeAs it overrides', function () {
    $domainTypes = ErrorTypescript::domainTypesFor(new ServerConfiguration(), typescriptDefinition('declaresRenamedThrows'));

    expect($domainTypes)->toBe('"renamed_failure"|"overridden_failure"')
        ->and($domainTypes)->not->toContain('domain_failure');
});

test('an exception declared by both the operation and a middleware appears once', function () {
    $domainTypes = ErrorTypescript::domainTypesFor(
        new ServerConfiguration(),
        typescriptDefinition('declaresRenamedThrows', [RenamingMiddleware::class]),
    );

    expect($domainTypes)->toBe('"renamed_failure"|"overridden_failure"|"renamed_middleware_failure"')
        ->and($domainTypes)->not->toContain('middleware_name');
});

/**
 * `never` is not an absence the caller has to handle - it is what makes the 400 branch vanish from
 * that operation's Failure, so the erasure and the emptiness are the same fact.
 */
test('an operation declaring nothing exposable exposes never', function () {
    expect(ErrorTypescript::domainTypesFor(new ServerConfiguration(), typescriptDefinition('declaresNothing')))
        ->toBe(ErrorTypescript::NO_DOMAIN_TYPES);
});

test('a #[Throws] lacking ExposeAs contributes no name', function () {
    // declaresThrows declares UnexposedException alongside ExposedDomainException, so only the
    // exposed one may be listed.
    $domainTypes = ErrorTypescript::domainTypesFor(new ServerConfiguration(), typescriptDefinition('declaresThrows'));

    expect($domainTypes)->toBe('"domain_failure"')
        ->and($domainTypes)->not->toContain('UnexposedException');
});

/* The declarations themselves. */

test('the catalogue declares one envelope per branch, and the client one the server never sends', function () {
    expect(ErrorTypescript::envelopeDeclarations())
        ->toContain('export type InvalidInputError = {code: 422, type: "INVALID_INPUT", details: {fields: Record<string, string[]>}};')
        ->toContain('export type AuthenticationError = {code: 401, type: "AUTHENTICATION_ERROR"};')
        ->toContain('export type AuthorizationError = {code: 403, type: "AUTHORIZATION_ERROR"};')
        ->toContain('export type NotFoundError = {code: 404, type: "NOT_FOUND"};')
        ->toContain('export type InternalError = {code: 500, type: "INTERNAL_ERROR"};')
        ->toContain('export type ClientError = {code: 0, type: "CLIENT_ERROR", cause: Error};');
});

/**
 * The conditional is what makes `never` mean "no 400 branch" rather than "a 400 whose name is
 * uninhabited". The brackets keep it from distributing, so two exposed names stay one member with a
 * union under details.type instead of splitting into two.
 */
test('the domain envelope erases itself rather than describing an uninhabited 400', function () {
    expect(ErrorTypescript::envelopeDeclarations())
        ->toContain('export type DomainError<TType extends string> = [TType] extends [never] ? never : {code: 400, type: "DOMAIN_ERROR", details: {type: TType}};');
});

/**
 * Declared even where unreachable: a name a consumer writes a handler against costs nothing to
 * reserve, whereas a Failure naming a branch this server cannot produce would be a lie.
 */
test('every name the catalogue lists is a name it declares', function () {
    foreach (ErrorTypescript::envelopeNames() as $name) {
        expect(ErrorTypescript::envelopeDeclarations())->toContain("export type {$name}");
    }
});

test('an envelope is named without its type argument, which is what an import statement takes', function () {
    foreach (ErrorTypescript::envelopeNames() as $name) {
        expect($name)->not->toContain('<');
    }
});
