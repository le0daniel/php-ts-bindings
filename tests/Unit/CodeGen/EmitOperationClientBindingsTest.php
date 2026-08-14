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
        new ServerMetadata('/query/{key}', '/command/{key}', new ServerConfiguration()),
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
    // A transport resolves to the raw response and never names the envelope, so there is nothing
    // for it to reach for.
    'OperationClient' => ['OperationClient', []],
    'DefaultClient' => ['DefaultClient', [
        './lib/OperationClient' => ['values' => [], 'types' => ['OperationClient', 'OperationOptions']],
    ]],
    'OperationException' => ['OperationException', [
        './lib/types' => ['values' => [], 'types' => ['ClientError', 'Failure']],
    ]],
    // DefaultClient is constructed, so it is a value import; a type only import would leave
    // `new DefaultClient(...)` referencing nothing at runtime. The guard is a value too: it runs
    // against every body, whatever transport produced it.
    'bindings' => ['bindings', [
        './lib/DefaultClient' => ['values' => ['DefaultClient'], 'types' => []],
        './lib/OperationClient' => ['values' => [], 'types' => ['OperationClient', 'OperationOptions']],
        './lib/types' => ['values' => [], 'types' => ['Failure', 'Result']],
        './lib/utils' => ['values' => ['isValidEnvelop'], 'types' => []],
    ]],
]);

/**
 * A transport moves bytes: it resolves to the status line and the parsed body, with no claim about
 * either. Only executeOperation speaks in envelopes — it gates the body through the guard and mints
 * the client branch, so what an operation exposed never concerns any transport.
 */
test('the transport resolves to the raw response, only the binding speaks in envelopes', function (string $file, string $signature) {
    expect(bindingFiles()[$file]->toString())->toContain($signature)
        ->and(bindingFiles()[$file]->toString())->not->toContain('{code: number}');
})->with([
    'the interface' => ['OperationClient', '): Promise<{status: number; jsonBody: unknown}>;'],
    'the implementation' => ['DefaultClient', 'options?: OperationOptions): Promise<{status: number; jsonBody: unknown}>'],
    'the binding' => ['bindings', 'options?: OperationOptions): Promise<Result<O, TDomainType>>'],
]);

/**
 * No type parameters and no envelope: nothing an implementation returns is trusted anyway — the
 * binding validates the body whoever produced it — so the interface promises nothing it would have
 * to take back.
 */
test('the transport takes no type parameters and never names the envelope', function () {
    expect(bindingFiles()['OperationClient']->toString())
        ->toContain('execute(')
        ->not->toContain('execute<')
        ->not->toContain('Result');
});

/**
 * The default transport is one honest fetch: no guard, no catch, no observation. Whatever it throws
 * is the binding's to catch, and whatever comes back is handed over exactly as received — the
 * status riding along unconsulted next to the parsed body.
 */
test('the default transport neither guards, catches, nor observes', function () {
    expect(bindingFiles()['DefaultClient']->toString())
        ->toContain('const jsonBody: unknown = await response.json();')
        ->toContain('return {status: response.status, jsonBody};')
        ->not->toContain('isValidEnvelop')
        ->not->toContain('try {')
        ->not->toContain('CLIENT_ERROR')
        ->not->toContain('Hook')
        ->not->toContain('registerHook')
        ->not->toContain('response.ok');
});

/**
 * Hooks are first party: they live in the bindings and see the envelope of every operation,
 * whichever client — the module global or a per call options.client — served it. Typed against the
 * widest domain union, because a hook observes any operation's envelope, not one operation's.
 */
test('hooks are registered on the bindings and typed against the whole catalogue', function () {
    expect(bindingFiles()['bindings']->toString())
        ->toContain("export type Hook = (result: Result<unknown, string>, operation: {type: 'query'|'command'; key: string}) => Promise<void> | void;")
        ->toContain('export function registerHook(hook: Hook): () => void {')
        ->toContain("async function callHooks<const T extends Result<unknown, string>>(result: T, operation: {type: 'query'|'command'; key: string}): Promise<T> {");
});

/**
 * Whatever went wrong is carried, not summarised: an AbortError has to arrive as the DOMException it
 * was, because throwOnFailure rethrows exactly that one and a re-wrapped copy would not be it.
 */
test('a throw anywhere below becomes the client envelope, keeping the original as its cause', function () {
    expect(bindingFiles()['bindings']->toString())
        ->toContain('const cause = e instanceof Error ? e : new Error(String(e));')
        ->toContain('return await callHooks(mintClientError(cause), operation);');
});

/**
 * The status line is never consulted: a CSRF middleware answering 419 with its own JSON, a proxy
 * answering 502 with an HTML page, or a framework writing a 200 around garbage all set whatever
 * status they like. Only the body can prove the server answered, so every body — from whatever
 * transport — goes through the envelope guard and is returned exactly as parsed when it passes.
 */
test('every response goes through the envelope guard, whatever the status line said', function () {
    expect(bindingFiles()['bindings']->toString())
        ->toContain('if (isValidEnvelop(jsonBody)) {')
        ->toContain('return await callHooks(jsonBody as Result<O, TDomainType>, operation);')
        ->not->toContain('response.ok');
});

/**
 * What was actually received survives on the minted envelope: the status always, the parsed body
 * only when there was one to parse — an absent key rather than an undefined value.
 */
test('a response that is not the envelope becomes the client branch carrying what was received', function () {
    expect(bindingFiles()['bindings']->toString())
        ->toContain('new Error(`Invalid response envelope (HTTP status ${status})`),')
        ->toContain('? {httpStatusCode: status}')
        ->toContain(': {httpStatusCode: status, jsonResponse: jsonBody},');
});

/**
 * executeOperation resolves, never rejects: no client at all, a transport that threw, a body that is
 * not the envelope — each becomes the client branch of the envelope, and the hooks see every one of
 * them, the valid answer included. One resolution site is what makes that a guarantee rather than a
 * convention each transport reimplements.
 */
test('executeOperation never throws, and hooks see every exit path', function () {
    $bindings = bindingFiles()['bindings']->toString();

    expect($bindings)
        ->toContain('const activeClient = options?.client ?? client;')
        ->toContain("return await callHooks(mintClientError(new Error('No client set')), operation);")
        ->not->toContain("throw new Error('No client set')")
        ->not->toContain('& {client?: OperationClient}')
        ->and(substr_count($bindings, 'return await callHooks('))->toBe(4);
});

/**
 * Nothing narrows the catalogue down to one branch here, so nothing has to name one: the exception
 * sees whatever the server can produce.
 */
test('the exception is typed against the whole catalogue', function () {
    expect(bindingFiles()['OperationException']->toString())
        ->toContain('export class OperationException<TDomainType extends string = string> extends Error {')
        ->toContain('public readonly cause: Failure<TDomainType>;')
        ->toContain('public static is<TDomainType extends string = string>(e: unknown): e is OperationException<TDomainType> {');
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
