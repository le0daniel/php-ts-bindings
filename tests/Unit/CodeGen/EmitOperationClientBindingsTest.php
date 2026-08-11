<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperationClientBindings;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypes;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypeUtils;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptImport;
use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;

/**
 * The four files are fixed runtime code, so no operation has to exist for them to be emitted. The
 * specifiers below are the ones an emitter writes — a lib file is always named the way a module at
 * the output root reaches it. Rewriting them for a file that lands in lib/ itself is the
 * orchestrator's job and is pinned in TypescriptServerCodeGeneratorTest.
 *
 * @return array<string, TypescriptFile>
 */
function bindingFiles(): array
{
    $emitter = new EmitOperationClientBindings();
    $emitter->setDependencies([
        EmitTypes::class => new EmitTypes(),
        EmitTypeUtils::class => new EmitTypeUtils(),
    ]);

    return $emitter->emitFiles(
        [],
        new ServerMetadata('/query/{fqn}', '/command/{fqn}', new ServerConfiguration()),
        new AliasRegistry(),
    );
}

test('emits the four client files', function () {
    expect(array_keys(bindingFiles()))
        ->toBe(['OperationClient', 'DefaultClient', 'OperationException', 'bindings']);
});

test('declares exactly the imports its body needs', function (string $file, array $expected) {
    $imports = [];
    foreach (bindingFiles()[$file]->imports as $import) {
        $imports[$import->from] = ['values' => $import->values, 'types' => $import->types];
    }

    expect($imports)->toBe($expected);
})->with([
    'OperationClient' => ['OperationClient', [
        './lib/types' => ['values' => [], 'types' => ['Result']],
    ]],
    'DefaultClient' => ['DefaultClient', [
        './lib/OperationClient' => ['values' => [], 'types' => ['OperationClient', 'OperationOptions']],
        './lib/types' => ['values' => [], 'types' => ['Failure', 'Result']],
        './lib/utils' => ['values' => ['isValidEnvelop'], 'types' => []],
    ]],
    'OperationException' => ['OperationException', [
        './lib/types' => ['values' => [], 'types' => ['ClientError', 'Failure']],
    ]],
    // DefaultClient is constructed, so it is a value import; a type only import would leave
    // `new DefaultClient(...)` referencing nothing at runtime.
    'bindings' => ['bindings', [
        './lib/DefaultClient' => ['values' => ['DefaultClient'], 'types' => []],
        './lib/OperationClient' => ['values' => [], 'types' => ['OperationClient', 'OperationOptions']],
        './lib/types' => ['values' => [], 'types' => ['Result']],
    ]],
]);

/**
 * A request can fail before it ever reaches the server, so the transport can always hand back the
 * client envelope — and it needs no mention in any of these signatures, because the branch is part of
 * every Failure whatever the operation exposed. What the caller chooses is only which domain names
 * the 400 branch carries, so that is all the transport takes.
 */
test('the transport takes the exposed names, not a failure shape', function (string $file, string $signature) {
    expect(bindingFiles()[$file]->toString())->toContain($signature)
        ->and(bindingFiles()[$file]->toString())->not->toContain('{code: number}');
})->with([
    'the interface' => ['OperationClient', 'execute<O, TDomainType extends string = never>('],
    'the implementation' => ['DefaultClient', 'options?: OperationOptions): Promise<Result<O, TDomainType>>'],
    'the binding' => ['bindings', 'options?: OperationOptions & {client?: OperationClient}): Promise<Result<O, TDomainType>>'],
]);

/**
 * Nothing narrows the catalogue down to one branch here, so nothing has to name one: the exception
 * and the hook see whatever the server can produce.
 */
test('a hook and the exception are typed against the whole catalogue', function () {
    expect(bindingFiles()['DefaultClient']->toString())
        ->toContain('export type Hook = (result: Result<unknown, string>) => Promise<void> | void;')
        ->toContain('private async callHooks<const T extends Result<unknown, string>>(result: T) {')
        ->and(bindingFiles()['OperationException']->toString())
        ->toContain('export class OperationException<TDomainType extends string = string> extends Error {')
        ->toContain('public readonly cause: Failure<TDomainType>;')
        ->toContain('public static is<TDomainType extends string = string>(e: unknown): e is OperationException<TDomainType> {');
});

/**
 * Whatever went wrong is carried, not summarised: an AbortError has to arrive as the DOMException it
 * was, because throwOnFailure rethrows exactly that one and a re-wrapped copy would not be it.
 */
test('a throw anywhere in the request becomes the client envelope, keeping the original as its cause', function () {
    expect(bindingFiles()['DefaultClient']->toString())
        ->toContain('const cause = e instanceof Error ? e : new Error(String(e));')
        ->toContain("const envelop = {success: false, code: 0, type: 'CLIENT_ERROR', cause} satisfies Failure;")
        ->toContain('return await this.callHooks(envelop);');
});

/**
 * The status line is never consulted: a CSRF middleware answering 419 with its own JSON, a proxy
 * answering 502 with an HTML page, or a framework writing a 200 around garbage all set whatever
 * status they like. Only the body can prove the server answered, so every response — ok or not —
 * goes through the envelope guard and is returned exactly as parsed when it passes.
 */
test('every response goes through the envelope guard, whatever the status line said', function () {
    expect(bindingFiles()['DefaultClient']->toString())
        ->toContain('const json: unknown = await response.json().catch(() => undefined);')
        ->toContain('if (isValidEnvelop(json)) {')
        ->toContain('return await this.callHooks(json as Result<O, TDomainType>);')
        ->not->toContain('response.ok')
        ->not->toContain('json?.code ?? response.status')
        ->not->toContain("json?.type ?? 'INTERNAL_ERROR'");
});

/**
 * What was actually received survives on the minted envelope: the status always, the parsed body
 * only when there was one to parse — an absent key rather than an undefined value.
 */
test('a response that is not the envelope becomes the client branch carrying what was received', function () {
    expect(bindingFiles()['DefaultClient']->toString())
        ->toContain('cause: new Error(`Invalid response envelope (HTTP status ${response.status})`),')
        ->toContain('? {httpStatusCode: response.status}')
        ->toContain(': {httpStatusCode: response.status, jsonResponse: json},');
});

/**
 * Zero is a real code, assigned by the client itself. A method rather than a getter, because
 * TypeScript allows a type predicate only on a function — and the predicate is what narrows
 * `cause` to the client branch at the call site.
 */
test('the exception narrows to the client branch through a type-guard method', function () {
    expect(bindingFiles()['OperationException']->toString())
        ->toContain('public isClientError(): this is OperationException<TDomainType> & {cause: Failure<TDomainType> & ClientError} {')
        ->toContain('return this.cause.code === 0;')
        ->not->toContain('get isClientError')
        ->not->toContain('!code ||');
});

/**
 * The shape is declared once, in the types file, and referenced everywhere else. A second literal
 * here would be a second definition free to drift from the one operations are typed against.
 */
test('no client file restates the envelope type it imports', function () {
    // The runtime literal it constructs is not the declaration: that one lives in the types file,
    // and a second copy here would be free to drift from what operations are typed against.
    foreach (bindingFiles() as $file) {
        expect($file->code)->not->toContain('type: "CLIENT_ERROR"')
            ->and($file->code)->not->toContain('cause: Error');
    }
});

/**
 * The transport moves a request and returns the envelope. Whatever a Client implementation puts next
 * to the data is that implementation's business, and naming it here would make the interface an
 * accomplice to one particular schema.
 */
test('the transport knows nothing about client directives', function () {
    foreach (bindingFiles() as $file) {
        expect($file->toString())
            ->not->toContain('WithClientDirectives')
            ->not->toContain('__client');
    }
});

test('throwOnFailure is not here — it narrows the envelope, not the transport', function () {
    expect(bindingFiles()['bindings']->toString())->not->toContain('throwOnFailure');
});

test('imports nothing its body does not reference', function () {
    $unused = [];
    foreach (bindingFiles() as $name => $file) {
        foreach ($file->imports as $import) {
            foreach ([...$import->values, ...$import->types] as $imported) {
                if (! str_contains($file->code, $imported)) {
                    $unused[] = "{$name} imports {$imported} from {$import->from}";
                }
            }
        }
    }

    expect($unused)->toBe([]);
});

/**
 * A raw import line inside a body is invisible to TypescriptFile: it is neither merged with what the
 * other generators contribute to the same file nor rewritten once the file lands in lib/. Imports go
 * through TypescriptImport, which is what makes both of those work.
 */
test('no body hand-writes an import line', function () {
    $raw = [];
    foreach (bindingFiles() as $name => $file) {
        if (str_contains($file->code, 'import ')) {
            $raw[] = $name;
        }
    }

    expect($raw)->toBe([]);
});

// utils is allowed alongside types: the envelope guard the transport gates every response through
// is the utils generator's, declared as a dependency the same way EmitTypes is.
test('every import names a file this generator emits, the types file, or the utils file', function () {
    $emitted = array_keys(bindingFiles());

    $unknown = [];
    foreach (bindingFiles() as $file) {
        foreach ($file->imports as $import) {
            $name = str_replace('./lib/', '', $import->from);
            if ($name !== 'types' && $name !== 'utils' && ! in_array($name, $emitted, true)) {
                $unknown[] = $import->from;
            }
        }
    }

    expect($unknown)->toBe([]);
});

test('the import methods name the files it emits', function () {
    $emitter = new EmitOperationClientBindings();

    expect($emitter->importFromBindings(values: ['executeOperation']))
        ->toEqual(TypescriptImport::values('./lib/bindings', 'executeOperation'))
        ->and($emitter->importFromOperationClient(types: ['OperationOptions']))
        ->toEqual(TypescriptImport::types('./lib/OperationClient', 'OperationOptions'))
        ->and($emitter->importFromDefaultClient(values: ['DefaultClient']))
        ->toEqual(TypescriptImport::values('./lib/DefaultClient', 'DefaultClient'))
        ->and($emitter->importFromOperationException(values: ['OperationException']))
        ->toEqual(TypescriptImport::values('./lib/OperationException', 'OperationException'));
});
