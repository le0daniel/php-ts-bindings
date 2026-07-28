<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypes;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Tests\Mocks\ValueObjects\Email;
use Tests\Mocks\ValueObjects\Slug;
use Tests\Mocks\ValueObjects\UserId;

function emitTypesFor(string $inputType, string $outputType): string
{
    $parser = new TypeParser();
    $operation = new Operation(
        key: 'users.get',
        definition: new Definition(OperationType::QUERY, Email::class, 'getUser', 'get', 'users', []),
        input: $parser->parse($inputType),
        output: $parser->parse($outputType),
    );

    $files = new EmitTypes()->emitFiles(
        [new TypedOperation('', '', '', $operation)],
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
