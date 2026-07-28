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
use Le0daniel\PhpTsBindings\Typescript\Data\Options;
use Le0daniel\PhpTsBindings\Typescript\Data\TypeRegistry;
use Le0daniel\PhpTsBindings\Typescript\TypescriptGenerator;
use Tests\Mocks\ValueObjects\Email;
use Tests\Mocks\ValueObjects\Slug;
use Tests\Mocks\ValueObjects\UserId;

/**
 * Mirrors how TypescriptServerCodeGenerator builds a TypedOperation: both directions collect their
 * aliases into one registry, which is what EmitTypes reads.
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
    $input = $generator->toTypescript($operation->inputNode(), IO::INPUT, new Options(registry: new TypeRegistry()));
    $output = $generator->toTypescript($operation->outputNode(), IO::OUTPUT, new Options(registry: $input->registry));

    $files = new EmitTypes()->emitFiles(
        [new TypedOperation($input->type, $output->type, '', $operation, $output->registry)],
        new ServerMetadata('/query/{fqn}', '/command/{fqn}'),
    );

    return $files['types'];
}

test('branded value objects are exported as branded typescript types', function () {
    $types = emitTypesFor(
        'array{id: \\' . UserId::class . '}',
        'array{email: \\' . Email::class . ', slug: \\' . Slug::class . '}',
    );

    // EmitTypes ucfirst()s the brand name for the alias, so the camelCase brand tag
    // "customerId" becomes the exported type CustomerId.
    expect($types)
        ->toContain('export type CustomerId = number & Brand<"customerId">')
        ->toContain('export type Email = string & Brand<"email">')
        // Slug carries no #[Brand], so it must not produce an exported alias.
        ->not->toContain('Slug');
});

test('branded value objects nested in lists and unions are still collected', function () {
    $types = emitTypesFor(
        'array{ids: list<\\' . UserId::class . '>}',
        'array{email: ?\\' . Email::class . '}',
    );

    expect($types)
        ->toContain('export type CustomerId = number & Brand<"customerId">')
        ->toContain('export type Email = string & Brand<"email">');
});

test('the existing BrandedString utility type still emits alongside value objects', function () {
    $types = emitTypesFor(
        'array{token: BrandedString<\'token\'>}',
        'array{email: \\' . Email::class . '}',
    );

    expect($types)
        ->toContain('export type Token = string & Brand<"token">')
        ->toContain('export type Email = string & Brand<"email">');
});
