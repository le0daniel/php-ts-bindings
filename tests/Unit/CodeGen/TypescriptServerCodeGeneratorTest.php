<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperationClientBindings;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperations;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypes;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypeUtils;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Helpers\TypeScriptFile;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptServerCodeGenerator;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\PlainlyExposedKeyGenerator;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedRegistry;
use Le0daniel\PhpTsBindings\Server\Server;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnsupportedTypeException;
use Tests\Unit\CodeGen\Mocks\ConflictingNamedOperations;
use Tests\Unit\CodeGen\Mocks\NamedOperations;
use Tests\Unit\CodeGen\Mocks\UnrepresentableOperations;
use Tests\Unit\CodeGen\Mocks\UserOperations;

/**
 * @param list<class-string> $classes
 * @return array<string, TypeScriptFile>
 */
function generateFor(array $classes): array
{
    $server = new Server(
        EagerlyLoadedRegistry::withClasses($classes, keyGenerator: new PlainlyExposedKeyGenerator()),
        [],
    );

    return new TypescriptServerCodeGenerator(
        [
            new EmitTypes(),
            new EmitOperationClientBindings(),
            new EmitTypeUtils(),
            new EmitOperations(),
        ],
    )->generate($server, new ServerMetadata('/query/{fqn}', '/command/{fqn}'));
}

test('attribute brands declare no aliases in lib/types.ts, only the Brand helper', function () {
    $files = generateFor([UserOperations::class]);

    expect($files)->toHaveKey('lib/types.ts')
        ->and($files['lib/types.ts']->toString())
        ->toContain('export type Brand<TBrand extends string>')
        ->not->toContain('export type CustomerId')
        ->not->toContain('export type Email')
        ->not->toContain('Slug');
});

test('renders brands inline in the operation types and imports the Brand helper', function () {
    $files = generateFor([UserOperations::class]);
    $operations = $files['users.ts']->toString();

    expect($operations)
        ->toContain('export type GetInput = {id:(number & Brand<"customerId">);};')
        ->toContain('export type GetResult = {email:(string & Brand<"email">);slug:string;};')
        ->toContain('export type CreateInput = {name:string;};')
        ->toContain('export type CreateResult = {id:(number & Brand<"customerId">);};')
        ->toContain("import type {Brand} from './lib/types';");
});

test('declares named types once in lib/types.ts, nested aliases and inline brands included', function () {
    $files = generateFor([NamedOperations::class]);

    expect($files['lib/types.ts']->toString())
        ->toContain('export type Customer = {email:(string & Brand<"email">);name:string;}')
        ->toContain('export type Order = {customer:Customer;id:(number & Brand<"customerId">);}')
        ->toContain('export type OrderStatus = ("OPEN"|"SHIPPED")')
        ->not->toContain('export type Email')
        ->not->toContain('export type CustomerId');
});

test('references named types by alias and imports every alias the operation relies on', function () {
    $operations = generateFor([NamedOperations::class])['orders.ts']->toString();

    // Every alias in the operation's registries is imported — Customer comes along as Order's
    // dependency. Brand is always imported; a linter drops it where unused. Email is an unnamed
    // brand, so it is no alias at all and never appears.
    expect($operations)
        ->toContain('export type GetResult = Order;')
        ->toContain('export type GetInput = {status:OrderStatus;};')
        ->toContain('export type StatusResult = {status:OrderStatus;};')
        ->toContain('export type StatusInput = {id:(number & Brand<"customerId">);};')
        ->toContain("import type {Brand, Customer, Order, OrderStatus} from './lib/types'")
        ->not->toContain('Email');
});

test('fails the run when two classes resolve to the same name with different shapes', function () {
    expect(fn() => generateFor([ConflictingNamedOperations::class]))
        ->toThrow(UnsupportedTypeException::class, 'Customer');
});

test('fails the whole run when an operation input has no TypeScript representation', function () {
    expect(fn() => generateFor([UnrepresentableOperations::class]))
        ->toThrow(UnsupportedTypeException::class, 'SomeFileInterface');
});
