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
use Le0daniel\PhpTsBindings\Typescript\Data\Options;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnsupportedTypeException;
use Tests\Unit\CodeGen\Mocks\UnrepresentableOperations;
use Tests\Unit\CodeGen\Mocks\UserOperations;

/**
 * @param list<class-string> $classes
 * @return array<string, TypeScriptFile>
 */
function generateFor(array $classes, Options $options = new Options()): array
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
        options: $options,
    )->generate($server, new ServerMetadata('/query/{fqn}', '/command/{fqn}'));
}

test('declares every referenced brand once in lib/types.ts', function () {
    $files = generateFor([UserOperations::class]);

    expect($files)->toHaveKey('lib/types.ts')
        ->and($files['lib/types.ts']->toString())
        ->toContain('export type CustomerId = number & Brand<"customerId">')
        ->toContain('export type Email = string & Brand<"email">')
        // Slug carries no #[Brand], so it registers no alias.
        ->not->toContain('Slug');
});

test('references brands by alias in the operation types and imports them', function () {
    $files = generateFor([UserOperations::class]);
    $operations = $files['users.ts']->toString();

    expect($operations)
        ->toContain('export type GetInput = {id:CustomerId;};')
        ->toContain('export type GetResult = {email:Email;slug:string;};')
        ->toContain('export type CreateInput = {name:string;};')
        ->toContain('export type CreateResult = {id:CustomerId;};')
        ->toContain("import {type CustomerId, type Email} from './lib/types';");
});

test('emits the backing primitives and no type import when brands are ignored', function () {
    $files = generateFor([UserOperations::class], new Options(ignoreBrandedTypes: true));
    $operations = $files['users.ts']->toString();

    expect($operations)
        ->toContain('export type GetInput = {id:number;};')
        ->toContain('export type GetResult = {email:string;slug:string;};')
        ->not->toContain("from './lib/types'")
        ->and($files['lib/types.ts']->toString())
        ->not->toContain('export type CustomerId')
        ->not->toContain('export type Email');
});

test('fails the whole run when an operation input has no TypeScript representation', function () {
    expect(fn() => generateFor([UnrepresentableOperations::class]))
        ->toThrow(UnsupportedTypeException::class, 'SomeFileInterface');
});
