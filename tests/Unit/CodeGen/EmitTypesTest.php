<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypes;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Typescript\Data\IO;
use Le0daniel\PhpTsBindings\Typescript\Data\TypeRegistry;
use Le0daniel\PhpTsBindings\Typescript\Data\TypeScript;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnsupportedTypeException;
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
    $registry = new TypeRegistry();
    $input = $generator->toTypescript($operation->inputNode(), IO::INPUT, $registry);
    $output = $generator->toTypescript($operation->outputNode(), IO::OUTPUT, $registry);

    $files = new EmitTypes()->emitFiles(
        [new TypedOperation($input, $output, TypeScript::fromRawString(''), $operation)],
        new ServerMetadata('/query/{fqn}', '/command/{fqn}'),
        $registry,
    );

    return $files['types'];
}

test('rejects an alias colliding with a declaration the types file always contains', function (string $alias) {
    $registry = new TypeRegistry([$alias => '{a:string;}']);

    expect(fn() => new EmitTypes()->emitFiles([], new ServerMetadata('/query/{fqn}', '/command/{fqn}'), $registry))
        ->toThrow(UnsupportedTypeException::class, 'collides with a declaration');
})->with([
    'the Brand helper generic' => ['Brand'],
    'the Result envelope' => ['Result'],
    'the TYPE_MAP constant' => ['TYPE_MAP'],
]);

test('attribute brands stay inline and declare no alias, only the Brand helper is exported', function () {
    $types = emitTypesFor(
        'array{id: \\' . UserId::class . '}',
        'array{email: \\' . Email::class . ', slug: \\' . Slug::class . '}',
    );

    expect($types)
        ->toContain('export type Brand<TBrand extends string>')
        ->not->toContain('export type CustomerId')
        ->not->toContain('export type Email')
        ->not->toContain('Slug');
});

test('named types are exported once, nested aliases and inline brands included', function () {
    $types = emitTypesFor(
        'array{status: \\' . OrderStatus::class . '}',
        '\\' . Order::class,
    );

    expect($types)
        ->toContain('export type Customer = {email:(string & Brand<"email">);name:string;}')
        ->toContain('export type Order = {customer:Customer;id:(number & Brand<"customerId">);}')
        ->toContain('export type OrderStatus = ("OPEN"|"SHIPPED")');
});

test('the BrandedString utility type keeps its implicit alias', function () {
    $types = emitTypesFor(
        'array{token: BrandedString<\'token\'>}',
        'array{email: \\' . Email::class . '}',
    );

    expect($types)
        ->toContain('export type Token = (string & Brand<"token">)')
        ->not->toContain('export type Email');
});
