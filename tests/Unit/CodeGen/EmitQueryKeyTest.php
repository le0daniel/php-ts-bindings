<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Closure;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperations;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitQueryKey;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypeUtils;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Typescript\Data\Typescript;
use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;
use Tests\Mocks\ValueObjects\Email;

/**
 * The code block and the file it renders to — rendering imports is the file's business, so the
 * import statements are only observable through the rendered output. The input type it references
 * belongs to EmitOperations, so the dependency is wired up the way the generator does it.
 *
 * @param  (Closure(TypedOperation): string)|null  $nameGenerator
 * @return array{string, string}
 */
function queryKeyCodeFor(TypedOperation $typedOperation, ?Closure $nameGenerator = null): array
{
    $emitter = new EmitQueryKey();
    $emitter->setDependencies([
        EmitOperations::class => new EmitOperations($nameGenerator),
        EmitTypeUtils::class => new EmitTypeUtils(),
    ]);

    $file = $emitter->generateOperationCode(
        $typedOperation,
        new ServerMetadata('/query/{key}', '/command/{key}', new ServerConfiguration()),
    );

    return [$file->code, $file->toString()];
}

function queryOperation(): Operation
{
    $parser = new TypeParser();

    return new Operation(
        key: 'orders.get',
        definition: new Definition(OperationType::QUERY, Email::class, 'getOrder', 'get', 'orders', []),
        input: $parser->parse('array{id: int}'),
        output: $parser->parse('string'),
    );
}

test('references the input type EmitOperations exports instead of inlining the definition', function () {
    [$code, $rendered] = queryKeyCodeFor(new TypedOperation(
        new Typescript('{status:OrderStatus;}', new AliasRegistry(['OrderStatus' => '"OPEN"|"SHIPPED"'])),
        new Typescript('Order', new AliasRegistry(['Order' => '{id:number;}'])),
        'never',
        queryOperation(),
    ));

    // The alias lives in the type the same module already declares, so nothing has to be imported
    // for it here — EmitOperations owns that import.
    expect($code)->toContain('export function getQueryKey(input: GetInput)')
        ->and($rendered)->toContain("import {queryKey} from './lib/utils';")
        ->and($rendered)->not->toContain('./lib/types')
        ->and($rendered)->not->toContain('OrderStatus');
});

test('follows the naming rule of the EmitOperations it depends on', function () {
    [$code] = queryKeyCodeFor(
        new TypedOperation(
            Typescript::fromRawString('{id:number;}'),
            Typescript::fromRawString('string'),
            'never',
            queryOperation(),
        ),
        fn (TypedOperation $operation): string => 'orders'.ucfirst($operation->definition->name),
    );

    expect($code)->toContain('export function ordersGetQueryKey(input: OrdersGetInput)');
});
