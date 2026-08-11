<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypes;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Data\IO;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnsupportedTypeException;
use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;
use Le0daniel\PhpTsBindings\Typescript\TypescriptGenerator;
use Tests\Mocks\Named\Order;
use Tests\Mocks\Named\OrderStatus;
use Tests\Mocks\ValueObjects\Email;
use Tests\Mocks\ValueObjects\Slug;
use Tests\Mocks\ValueObjects\UserId;

/**
 * Mirrors how TypescriptServerCodeGenerator wires EmitTypes: both directions emit into the run's
 * shared registry, which is what the types file declares.
 */
function emitTypesFor(string $inputType, string $outputType): string
{
    $parser = new TypeParser();
    $operation = new Operation(
        key: 'users.get',
        definition: new Definition(OperationType::QUERY, Email::class, 'getUser', 'get', 'users', []),
        input: $parser->parse($inputType),
        output: $parser->parse($outputType),
    );

    $generator = new TypescriptGenerator();
    $registry = new AliasRegistry();
    $input = $generator->toTypescript($operation->inputNode(), IO::INPUT, $registry);
    $output = $generator->toTypescript($operation->outputNode(), IO::OUTPUT, $registry);

    $files = new EmitTypes()->emitFiles(
        [new TypedOperation($input, $output, 'never', $operation)],
        new ServerMetadata('/query/{fqn}', '/command/{fqn}', new ServerConfiguration()),
        $registry,
    );

    return $files['types']->toString();
}

test('rejects an alias colliding with a declaration the types file always contains', function (string $alias) {
    $registry = new AliasRegistry([$alias => '{a:string;}']);

    expect(fn () => new EmitTypes()->emitFiles([], new ServerMetadata('/query/{fqn}', '/command/{fqn}', new ServerConfiguration()), $registry))
        ->toThrow(UnsupportedTypeException::class, 'collides with a declaration');
})->with([
    'the Brand helper generic' => ['Brand'],
    'the Result envelope' => ['Result'],
    'the success branch' => ['Success'],
    'the failure branch' => ['Failure'],
    'the namespace union' => ['OperationNamespaces'],
    'the invalid input envelope' => ['InvalidInputError'],
    'the authentication envelope' => ['AuthenticationError'],
    'the authorization envelope' => ['AuthorizationError'],
    'the not found envelope' => ['NotFoundError'],
    'the domain envelope' => ['DomainError'],
    'the internal envelope' => ['InternalError'],
    'the client envelope' => ['ClientError'],
]);

/**
 * The reserved list and the declarations are two literals in EmitTypes, so this is the guard
 * against drift between them: a declaration added to the heredoc and forgotten in the reserved
 * list is a user alias that silently generates a second, conflicting declaration.
 */
test('every declaration the types file always contains is reserved', function () {
    $types = new EmitTypes()->emitFiles(
        [],
        new ServerMetadata('/query/{fqn}', '/command/{fqn}', new ServerConfiguration()),
        new AliasRegistry(),
    )['types']->toString();

    preg_match_all('/^export type (\w+)/m', $types, $matches);
    expect($matches[1])->not->toBe([]);

    foreach ($matches[1] as $name) {
        expect(fn () => new EmitTypes()->emitFiles(
            [],
            new ServerMetadata('/query/{fqn}', '/command/{fqn}', new ServerConfiguration()),
            new AliasRegistry([$name => '{a:string;}']),
        ))->toThrow(UnsupportedTypeException::class, 'collides with a declaration');
    }
});

/**
 * Failure is a union of references, so the shapes have to be declared here or nothing resolves them.
 * Generic over the exposed exception names for the one branch that varies.
 */
test('the finite error catalogue is declared in the types file', function () {
    $types = emitTypesFor(
        'array{id: \\'.UserId::class.'}',
        'array{email: \\'.Email::class.'}',
    );

    expect($types)
        ->toContain('export type InvalidInputError = {code: 422, type: "INVALID_INPUT", details: {fields: Record<string, string[]>}};')
        ->toContain('export type AuthenticationError = {code: 401, type: "AUTHENTICATION_ERROR"};')
        ->toContain('export type AuthorizationError = {code: 403, type: "AUTHORIZATION_ERROR"};')
        ->toContain('export type NotFoundError = {code: 404, type: "NOT_FOUND"};')
        ->toContain('export type DomainError<TType extends string> = [TType] extends [never] ? never : {code: 400, type: "DOMAIN_ERROR", details: {name: TType}};')
        ->toContain('export type InternalError = {code: 500, type: "INTERNAL_ERROR"};')
        ->toContain('export type ClientError = {code: 0, type: "CLIENT_ERROR", cause: Error};');
});

/**
 * The catalogue is closed, so Failure is the union of all of it rather than a hole for whatever a
 * caller passes. What remains parameterised is the only thing an operation can add to it: the
 * names it exposed.
 */
test('Failure is the union of the whole catalogue, not a type parameter', function () {
    $types = emitTypesFor(
        'array{id: \\'.UserId::class.'}',
        'array{email: \\'.Email::class.'}',
    );

    expect($types)
        ->toContain('export type Failure<TDomainType extends string = never> = {success: false, __metadata?: Record<string, unknown>} & (InvalidInputError|AuthenticationError|AuthorizationError|NotFoundError|DomainError<TDomainType>|InternalError|ClientError);')
        ->not->toContain('{code: number}');
});

/**
 * Which of an application's exceptions land in which category is runtime configuration, and the
 * union does not shrink around it: every branch is always reachable, whatever the server was
 * configured with.
 */
test('the auth branches are in Failure without any exceptions mapped onto them', function () {
    $types = emitTypesFor(
        'array{id: \\'.UserId::class.'}',
        'array{email: \\'.Email::class.'}',
    );

    preg_match('/^export type Failure.*$/m', $types, $matches);

    expect($matches[0])->toContain('AuthenticationError')
        ->toContain('AuthorizationError');
});

test('every name the failure union references is declared in the same file', function () {
    $types = emitTypesFor(
        'array{id: \\'.UserId::class.'}',
        'array{email: \\'.Email::class.'}',
    );

    preg_match('/^export type Failure.*& \((.*)\);$/m', $types, $matches);
    expect($matches[1] ?? '')->not->toBe('');

    foreach (explode('|', $matches[1]) as $reference) {
        expect($types)->toContain('export type '.strtok($reference, '<'));
    }
});

test('every ErrorType case has an envelope carrying its discriminant', function () {
    // The catalogue is a plain literal, so this is the guard against a category added to
    // ErrorType without a TypeScript shape to describe it.
    $types = emitTypesFor(
        'array{id: \\'.UserId::class.'}',
        'array{email: \\'.Email::class.'}',
    );

    foreach (ErrorType::cases() as $type) {
        expect($types)->toContain('type: "'.$type->name.'"');
    }
});

test('the envelope names the client side channel without describing what is in it', function () {
    $types = emitTypesFor(
        'array{id: \\'.UserId::class.'}',
        'array{email: \\'.Email::class.'}',
    );

    // The key is the library's own - RpcSuccess::jsonSerialize() writes it - so the envelope says
    // it may be there. The value is not: Client is an extension point, and a directive payload
    // belongs to the implementation that emits it, which for the one this library ships is
    // lib/client-operations-spa.ts.
    expect($types)
        ->toContain('export type Result<T, TDomainType extends string = never> = Success<T> | Failure<TDomainType>;')
        ->toContain('__client?: unknown')
        ->not->toContain('operations-spa')
        ->not->toContain('OperationsClientPayload')
        ->not->toContain('WithClientDirectives')
        ->not->toContain('ClientToast');
});

test('the branches declare exactly what jsonSerialize can put on each of them', function () {
    $types = emitTypesFor(
        'array{id: \\'.UserId::class.'}',
        'array{email: \\'.Email::class.'}',
    );

    // __metadata rides both outcomes: it is the core's own, always array<string, mixed>, written
    // only through withMetadata()/appendMetadata(). __client rides success alone, because RpcError
    // holds no Client - a toast queued before a throw must not reach the browser. Both optional,
    // because jsonSerialize() leaves either key off when there is nothing to say.
    expect($types)
        ->toContain('export type Success<T> = {success: true, data: T, __client?: unknown, __metadata?: Record<string, unknown>}')
        ->toContain('export type Failure<TDomainType extends string = never> = {success: false, __metadata?: Record<string, unknown>} & (');
});

test('attribute brands stay inline and declare no alias, only the Brand helper is exported', function () {
    $types = emitTypesFor(
        'array{id: \\'.UserId::class.'}',
        'array{email: \\'.Email::class.', slug: \\'.Slug::class.'}',
    );

    expect($types)
        ->toContain('export type Brand<TBrand extends string>')
        ->not->toContain('export type CustomerId')
        ->not->toContain('export type Email')
        ->not->toContain('Slug');
});

test('named types are exported once, nested aliases and inline brands included', function () {
    $types = emitTypesFor(
        'array{status: \\'.OrderStatus::class.'}',
        '\\'.Order::class,
    );

    expect($types)
        ->toContain('export type Customer = {email:(string & Brand<"email">);name:string;}')
        ->toContain('export type Order = {customer:Customer;id:(number & Brand<"customerId">);}')
        ->toContain('export type OrderStatus = ("OPEN"|"SHIPPED")');
});

test('the BrandedString utility type keeps its implicit alias', function () {
    $types = emitTypesFor(
        'array{token: BrandedString<\'token\'>}',
        'array{email: \\'.Email::class.'}',
    );

    expect($types)
        ->toContain('export type Token = (string & Brand<"token">)')
        ->not->toContain('export type Email');
});
