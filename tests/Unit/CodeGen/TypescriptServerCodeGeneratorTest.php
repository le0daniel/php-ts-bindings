<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperationClientBindings;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperations;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitQueryKey;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTanstackQuery;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypeMap;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypes;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypeUtils;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\CodeGen\Exceptions\InvalidGeneratorDependencies;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptServerCodeGenerator;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\PlainlyExposedKeyGenerator;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedOperationRegistry;
use Le0daniel\PhpTsBindings\Server\Server;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnsupportedTypeException;
use Tests\Unit\CodeGen\Mocks\AsymmetricNamedOperations;
use Tests\Unit\CodeGen\Mocks\ConflictingNamedOperations;
use Tests\Unit\CodeGen\Mocks\NamedOperations;
use Tests\Unit\CodeGen\Mocks\PerDirectionNamedOperations;
use Tests\Unit\CodeGen\Mocks\UnrepresentableOperations;
use Tests\Unit\CodeGen\Mocks\UserOperations;

/**
 * @param list<class-string> $classes
 * @param list<object> $generators
 * @return array<string, TypescriptFile>
 */
function generateFor(array $classes, ?array $generators = null): array
{
    $server = new Server(
        EagerlyLoadedOperationRegistry::withClasses($classes, keyGenerator: new PlainlyExposedKeyGenerator()),
    );

    return new TypescriptServerCodeGenerator(
        $generators ?? [
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

test('merges what every generator imports into one sorted block per module', function () {
    $operations = generateFor([NamedOperations::class], [
        new EmitTypes(),
        new EmitOperationClientBindings(),
        new EmitTypeUtils(),
        new EmitOperations(),
        new EmitQueryKey(),
        new EmitTanstackQuery(),
    ])['orders.ts']->toString();

    // Modules are sorted by specifier and each appears exactly once, however the generators ran:
    // bindings collects executeOperation and throwOnFailure, utils' queryKey is claimed twice and
    // deduped, and the aliases come from both EmitOperations and EmitQueryKey. Type only exports
    // are on their own line, which is what verbatimModuleSyntax requires.
    expect($operations)->toStartWith(<<<TypeScript
    import type {OperationOptions} from './lib/OperationClient';
    import {executeOperation, throwOnFailure} from './lib/bindings';
    import type {Brand, Customer, Order, OrderStatus} from './lib/types';
    import {queryKey} from './lib/utils';
    import type {UseQueryOptions} from '@tanstack/react-query';
    import {queryOptions, useQuery} from '@tanstack/react-query';

    TypeScript);
});

test('every generator names an operation the way the one that declares it does', function () {
    // The naming rule is handed to EmitOperations only. The other two ask it for the names, so a
    // rule set in one place cannot leave them referencing types and functions no file declares.
    $operations = generateFor([NamedOperations::class], [
        new EmitTypes(),
        new EmitOperationClientBindings(),
        new EmitTypeUtils(),
        new EmitOperations(fn(TypedOperation $operation): string => "orders" . ucfirst($operation->definition->name)),
        new EmitQueryKey(),
        new EmitTanstackQuery(),
    ])['orders.ts']->toString();

    expect($operations)
        ->toContain('export type OrdersGetInput = {status:OrderStatus;};')
        ->toContain('export async function ordersGet(input: OrdersGetInput, options?: OperationOptions)')
        ->toContain('export function ordersGetQueryKey(input: OrdersGetInput)')
        ->toContain('export function ordersGetQueryOptions(input: OrdersGetInput, options?: OrdersGetOptions)')
        ->toContain('export function useOrdersGetQuery(input: OrdersGetInput,')
        ->toContain('const result = await ordersGet(input, {signal});');
});

test('a lib file reaches its siblings directly instead of through lib/', function () {
    // What an emitter writes is './lib/x' — the way a module at the output root reaches it. A file
    // that lands in lib/ itself is one directory deeper, so the orchestrator resolves the specifier
    // once it knows where the file went, and the emitters never learn where that is.
    $files = generateFor([NamedOperations::class]);

    expect($files['lib/bindings.ts']->toString())->toStartWith(<<<TypeScript
    import {DefaultClient} from './DefaultClient';
    import type {OperationClient, OperationOptions} from './OperationClient';
    import {OperationException} from './OperationException';
    import type {Result, Success, WithClientDirectives} from './types';

    TypeScript);

    expect($files['lib/utils.ts']->toString())->toStartWith(
        "import type {ClientDirectives, ClientRedirect, ClientToast, SPAClientDirectives, WithClientDirectives} from './types';"
    );

    expect($files['lib/types.ts']->toString())->not->toContain('import ');
});

test('no lib file names a module through lib/', function () {
    $files = generateFor([NamedOperations::class], [
        new EmitTypes(),
        new EmitOperationClientBindings(),
        new EmitTypeUtils(),
        new EmitOperations(),
        new EmitTypeMap(),
        new EmitQueryKey(),
        new EmitTanstackQuery(),
    ]);

    $wrong = [];
    foreach ($files as $path => $file) {
        if (str_starts_with($path, 'lib/') && str_contains($file->toString(), "'./lib/")) {
            $wrong[] = $path;
        }
    }

    expect($wrong)->toBe([]);
});

test('the type map is written into the types file the types generator owns', function () {
    $files = generateFor([NamedOperations::class], [new EmitTypes(), new EmitTypeMap()]);

    // Two files: typemap inlines the aliases EmitTypes declares, so it only resolves while
    // it sits next to them.
    expect($files)->toHaveKey('lib/type-map.ts')
        ->and($files['lib/type-map.ts']->toString())
        ->toContain('export type TypeMap = {');
});

test('fails the run when a generator depends on one that is not registered', function () {
    expect(fn() => generateFor([NamedOperations::class], [
        new EmitTypes(),
        new EmitOperationClientBindings(),
        new EmitTanstackQuery(),
    ]))->toThrow(InvalidGeneratorDependencies::class);
});

test('fails the run when a generator imports from one that is not registered', function () {
    // Nothing declares './lib/types' by hand any more: the import comes from EmitTypes, so a run
    // without it cannot silently emit an operation module pointing at a file no one writes.
    expect(fn() => generateFor([NamedOperations::class], [
        new EmitOperationClientBindings(),
        new EmitOperations(),
    ]))->toThrow(InvalidGeneratorDependencies::class);
});

test('fails the run when two classes resolve to the same name with different shapes', function () {
    expect(fn() => generateFor([ConflictingNamedOperations::class]))
        ->toThrow(UnsupportedTypeException::class, 'Customer');
});

test('fails the whole run when an operation input has no TypeScript representation', function () {
    expect(fn() => generateFor([UnrepresentableOperations::class]))
        ->toThrow(UnsupportedTypeException::class, 'SomeFileInterface');
});

test('fails the run when one alias would have to describe two shapes', function () {
    // AstValidator runs before any pass, so this never reaches the emitter.
    expect(fn() => generateFor([AsymmetricNamedOperations::class]))
        ->toThrow(ParserException::class, 'resolves to one alias "AsymmetricNamed" for both directions');
});

test('a name per direction declares both shapes; a single shape is referenced both ways', function () {
    $files = generateFor([PerDirectionNamedOperations::class]);
    $types = $files['lib/types.ts']->toString();
    $operations = $files['articles.ts']->toString();

    expect($types)
        ->toContain('export type PerDirectionNamed = {visible:string;}')
        ->toContain('export type PerDirectionNamedInput = {secret:string;}')
        ->toContain('export type Customer = {email:(string & Brand<"email">);name:string;}');

    // The symmetric class is its alias in both directions — no inlined duplicate of the same shape.
    expect($operations)
        ->toContain('export type RoundtripInput = PerDirectionNamedInput;')
        ->toContain('export type RoundtripResult = PerDirectionNamed;')
        ->toContain('export type CustomerInput = Customer;')
        ->toContain('export type CustomerResult = Customer;');
});
