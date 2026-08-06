<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypes;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Data\IO;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Typescript\Data\Typescript;
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
        [new TypedOperation($input, $output, Typescript::fromRawString(''), $operation)],
        new ServerMetadata('/query/{fqn}', '/command/{fqn}'),
        $registry,
    );

    return $files['types']->toString();
}

test('rejects an alias colliding with a declaration the types file always contains', function (string $alias) {
    $registry = new AliasRegistry([$alias => '{a:string;}']);

    expect(fn () => new EmitTypes()->emitFiles([], new ServerMetadata('/query/{fqn}', '/command/{fqn}'), $registry))
        ->toThrow(UnsupportedTypeException::class, 'collides with a declaration');
})->with([
    'the Brand helper generic' => ['Brand'],
    'the Result envelope' => ['Result'],
    'the success branch' => ['Success'],
    'the failure branch' => ['Failure'],
    'the namespace union' => ['OperationNamespaces'],
]);

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
        ->toContain('export type Result<T, E extends {code: number} = never> = Success<T> | Failure<E>;')
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
        ->toContain('export type Failure<E extends {code: number}> = {success: false, __metadata?: Record<string, unknown>} & E;');
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
