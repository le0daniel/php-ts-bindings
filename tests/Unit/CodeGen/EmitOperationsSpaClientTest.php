<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperationsSpaClient;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Server\Data\ToastType;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptImport;
use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;

/**
 * The file describes one wire shape, so no operation has to exist for it to be emitted.
 *
 * @return array<string, TypescriptFile>
 */
function spaClientFiles(): array
{
    return new EmitOperationsSpaClient()->emitFiles(
        [],
        new ServerMetadata('/query/{key}', '/command/{key}', new ServerConfiguration()),
        new AliasRegistry(),
    );
}

test('emits one file, named the way a module at the output root reaches it', function () {
    expect(array_keys(spaClientFiles()))->toBe(['client-operations-spa']);

    expect(new EmitOperationsSpaClient()->importFromOperationsSpaClient(values: ['containsOperationSpaPayload']))
        ->toEqual(TypescriptImport::values('./lib/client-operations-spa', 'containsOperationSpaPayload'));
});

test('the payload type mirrors what OperationSPAClient serializes', function () {
    $toastTypes = implode('|', array_map(
        fn (ToastType $type): string => "'{$type->value}'",
        ToastType::cases(),
    ));

    // Every key is optional except the discriminator: OperationSPAClient only writes a key when
    // something called for it, and returns null when nothing did.
    expect(spaClientFiles()['client-operations-spa']->toString())
        ->toContain("export type ClientToast = {type: {$toastTypes}; message: string;};")
        ->toContain('export type ClientRedirect = {url: string; reload: boolean;};')
        ->toContain('export type ClientInvalidation = [string, ...unknown[]];')
        ->toContain('export type OperationsClientPayload = {')
        ->toContain('type: "operations-spa";')
        ->toContain('redirect?: ClientRedirect;')
        ->toContain('toasts?: ClientToast[];')
        ->toContain('invalidations?: ClientInvalidation[];');
});

test('an invalidation is a namespace followed by any number of keys, matching queryKey and PHP', function () {
    // Client::invalidate($namespace) emits a single element array, so requiring a second
    // string would describe a payload the server never produces.
    expect(spaClientFiles()['client-operations-spa']->toString())
        ->not->toContain('[string, string, ...unknown[]]');
});

test('the guard narrows on the discriminator alone', function () {
    // The payload is written in one pass, so a server that wrote the discriminator wrote the rest
    // of it. Walking every directive to prove that buys nothing at the boundary.
    expect(spaClientFiles()['client-operations-spa']->toString())
        ->toContain('export function containsOperationSpaPayload<const T>(value: T): value is T & {__client: OperationsClientPayload}')
        ->toContain("=== 'operations-spa'")
        ->not->toContain('isClientToast')
        ->not->toContain('isClientRedirect')
        ->not->toContain('isArrayOf');
});

test('the toast union is derived from the PHP enum, so it cannot drift', function () {
    // Whatever ToastType holds, the emitted union holds — nothing here restates the cases.
    $emitted = spaClientFiles()['client-operations-spa']->toString();

    foreach (ToastType::cases() as $case) {
        expect($emitted)->toContain("'{$case->value}'");
    }
});

/**
 * The module is self contained: it names no type it does not declare, so it survives a run where
 * every other generator is switched off.
 */
test('imports nothing', function () {
    $file = spaClientFiles()['client-operations-spa'];

    expect($file->imports)->toBe([])
        ->and($file->code)->not->toContain('import ');
});
