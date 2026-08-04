<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypes;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Data\IO;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\ToastType;
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

    expect(fn() => new EmitTypes()->emitFiles([], new ServerMetadata('/query/{fqn}', '/command/{fqn}'), $registry))
        ->toThrow(UnsupportedTypeException::class, 'collides with a declaration');
})->with([
    'the Brand helper generic' => ['Brand'],
    'the Result envelope' => ['Result'],
    'the client directive wrapper' => ['WithClientDirectives'],
    'the SPA client directives' => ['SPAClientDirectives'],
    'the directive payload' => ['ClientDirectives'],
    'the toast directive' => ['ClientToast'],
    'the redirect directive' => ['ClientRedirect'],
    'the invalidation directive' => ['ClientInvalidation'],
]);

test('the SPA client directives mirror the PHP client contract', function () {
    $types = emitTypesFor(
        'array{id: \\' . UserId::class . '}',
        'array{email: \\' . Email::class . '}',
    );

    $toastTypes = implode('|', array_map(
        fn(ToastType $type): string => "'{$type->value}'",
        ToastType::cases(),
    ));

    expect($types)
        ->toContain("export type ClientToast = {type: {$toastTypes}; message: string;};")
        ->toContain('export type ClientRedirect = {url: string; reload: boolean;};')
        ->toContain('export type ClientInvalidation = [string, ...unknown[]];')
        ->toContain('export type SPAClientDirectives<T> = T & {__client: ClientDirectives};')
        ->not->toContain('"soft"|"hard"')
        ->not->toContain('hardRedirect');
});

test('an invalidation is a namespace followed by any number of keys, matching queryKey and PHP', function () {
    $types = emitTypesFor('array{id: string}', 'array{id: string}');

    // Client::invalidate($namespace) emits a single element array, so requiring a second
    // string would describe a payload the server never produces.
    expect($types)->not->toContain('[string, string, ...unknown[]]');
});

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
