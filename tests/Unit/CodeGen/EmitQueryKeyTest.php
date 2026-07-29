<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitQueryKey;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Typescript\Data\TypeRegistry;
use Le0daniel\PhpTsBindings\Typescript\Data\TypeScript;
use Tests\Mocks\ValueObjects\Email;

/**
 * The code block and the file it renders to — rendering imports is the file's business, so the
 * import statements are only observable through the rendered output.
 *
 * @return array{string, string}
 */
function queryKeyCodeFor(TypedOperation $typedOperation): array
{
    $file = new EmitQueryKey()->generateOperationCode(
        $typedOperation,
        new ServerMetadata('/query/{fqn}', '/command/{fqn}'),
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

test('imports the aliases the inlined input definition carries', function () {
    [$code, $rendered] = queryKeyCodeFor(new TypedOperation(
        new TypeScript('{status:OrderStatus;}', new TypeRegistry(['OrderStatus' => '"OPEN"|"SHIPPED"'])),
        new TypeScript('Order', new TypeRegistry(['Order' => '{id:number;}'])),
        TypeScript::fromRawString(''),
        queryOperation(),
    ));

    expect($code)->toContain('export function getQueryKey(input: {status:OrderStatus;})')
        ->and($rendered)->toContain("import type {Brand, OrderStatus} from './lib/types';")
        // The output-only alias is not referenced by the query key.
        ->and($rendered)->not->toContain('Order,');
});

test('always imports the Brand helper, whether the input renders an inline brand or not', function () {
    [, $withBrand] = queryKeyCodeFor(new TypedOperation(
        new TypeScript('{id:number & Brand<"customerId">;}', new TypeRegistry()),
        TypeScript::fromRawString('string'),
        TypeScript::fromRawString(''),
        queryOperation(),
    ));

    [, $withoutBrand] = queryKeyCodeFor(new TypedOperation(
        TypeScript::fromRawString('{id:number;}'),
        TypeScript::fromRawString('string'),
        TypeScript::fromRawString(''),
        queryOperation(),
    ));

    expect($withBrand)->toContain("import type {Brand} from './lib/types';")
        ->and($withoutBrand)->toContain("import type {Brand} from './lib/types';");
});
