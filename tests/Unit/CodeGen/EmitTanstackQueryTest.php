<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Closure;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperationClientBindings;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperations;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTanstackQuery;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypeUtils;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Data\Typescript;
use Tests\Mocks\ValueObjects\Email;

/**
 * The emitter has no naming of its own: every name it references is one EmitOperations declared, so
 * wiring the dependency up is part of constructing it.
 *
 * @param  (Closure(TypedOperation): string)|null  $nameGenerator
 */
function tanstackCodeFor(TypedOperation $typedOperation, ?Closure $nameGenerator = null): ?TypescriptFile
{
    $emitter = new EmitTanstackQuery();
    $emitter->setDependencies([
        EmitOperations::class => new EmitOperations($nameGenerator),
        EmitTypeUtils::class => new EmitTypeUtils(),
        EmitOperationClientBindings::class => new EmitOperationClientBindings(),
    ]);

    return $emitter->generateOperationCode(
        $typedOperation,
        new ServerMetadata('/query/{key}', '/command/{key}', new ServerConfiguration()),
    );
}

function tanstackOperation(OperationType $type = OperationType::QUERY): Operation
{
    $parser = new TypeParser();

    return new Operation(
        key: 'orders.get',
        definition: new Definition($type, Email::class, 'getOrder', 'get', 'orders', []),
        input: $parser->parse('array{id: int}'),
        output: $parser->parse('string'),
    );
}

test('references the types and the function EmitOperations declared', function () {
    $code = tanstackCodeFor(new TypedOperation(
        Typescript::fromRawString('{id:number;}'),
        Typescript::fromRawString('string'),
        'never',
        tanstackOperation(),
    ))->code;

    expect($code)
        ->toContain('export function getQueryOptions(input: GetInput, options?: GetOptions)')
        ->toContain('type GetOptions = Omit<UseQueryOptions<GetResult>, ')
        ->toContain('queryFn: async ({signal}): Promise<GetResult> =>')
        ->toContain('const result = await get(input, {signal});')
        ->toContain('export function useGetQuery(input: GetInput, queryOptions?: Partial<GetOptions>)');
});

test('follows the naming rule of the EmitOperations it depends on', function () {
    // The closure lives on EmitOperations alone. Anything this emitter references has to come from
    // there, or it would emit calls to types and functions no file declares.
    $code = tanstackCodeFor(
        new TypedOperation(
            Typescript::fromRawString('{id:number;}'),
            Typescript::fromRawString('string'),
            'never',
            tanstackOperation(),
        ),
        fn (TypedOperation $operation): string => 'orders'.ucfirst($operation->definition->name),
    )->code;

    expect($code)
        ->toContain('export function ordersGetQueryOptions(input: OrdersGetInput, options?: OrdersGetOptions)')
        ->toContain('const result = await ordersGet(input, {signal});')
        ->toContain('export function useOrdersGetQuery(input: OrdersGetInput,')
        // Nothing falls back to the operation's own name.
        ->not->toContain('await get(')
        ->not->toContain('useGetQuery');
});

test('drops the input argument when the operation takes none', function () {
    $code = tanstackCodeFor(new TypedOperation(
        Typescript::fromRawString('null'),
        Typescript::fromRawString('string'),
        'never',
        tanstackOperation(),
    ))->code;

    expect($code)
        ->toContain('export function getQueryOptions(options?: GetOptions)')
        ->toContain("queryKey: queryKey('orders', 'get'),")
        ->toContain('const result = await get({signal});')
        ->toContain('export function useGetQuery(queryOptions?: Partial<GetOptions>)')
        ->not->toContain('GetInput');
});

test('emits nothing for a command', function () {
    expect(tanstackCodeFor(new TypedOperation(
        Typescript::fromRawString('{id:number;}'),
        Typescript::fromRawString('string'),
        'never',
        tanstackOperation(OperationType::COMMAND),
    )))->toBeNull();
});
