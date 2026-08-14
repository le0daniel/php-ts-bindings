<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperationClientBindings;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypes;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypeUtils;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Data\IO;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;
use Le0daniel\PhpTsBindings\Typescript\TypescriptGenerator;
use Tests\Mocks\ValueObjects\Email;

function emitUtilsFor(OperationType $type = OperationType::QUERY, string $namespace = 'users'): string
{
    $parser = new TypeParser();
    $operation = new Operation(
        key: "{$namespace}.get",
        definition: new Definition($type, Email::class, 'getUser', 'get', $namespace, []),
        input: $parser->parse('array{id: string}'),
        output: $parser->parse('array{id: string}'),
    );

    $generator = new TypescriptGenerator();
    $registry = new AliasRegistry();
    $input = $generator->toTypescript($operation->inputNode(), IO::INPUT, $registry);
    $output = $generator->toTypescript($operation->outputNode(), IO::OUTPUT, $registry);

    // The envelope it narrows and the exception it throws are declared elsewhere, so the
    // dependencies are wired up the way the generator does it.
    $emitter = new EmitTypeUtils();
    $emitter->setDependencies([
        EmitTypes::class => new EmitTypes(),
        EmitOperationClientBindings::class => new EmitOperationClientBindings(),
    ]);

    $files = $emitter->emitFiles(
        [new TypedOperation($input, $output, 'never', $operation)],
        new ServerMetadata('/query/{key}', '/command/{key}', new ServerConfiguration()),
        $registry,
    );

    return $files['utils']->toString();
}

test('query namespaces are emitted as a literal union', function () {
    expect(emitUtilsFor(namespace: 'orders'))->toContain("type QueryNamespaces = 'orders';");
});

test('throwOnFailure lives next to queryKey, not in the transport bindings', function () {
    // It narrows the envelope; it knows nothing about how a request was made. Keeping it here means
    // a project generating no transport bindings at all still gets it.
    expect(emitUtilsFor())
        ->toContain('export function throwOnFailure<const T>(result: Result<T, string>): asserts result is Success<T>')
        ->toContain('throw new OperationException(result);');
});

/**
 * A cancelled request is not a failed operation. Tanstack aborts the in flight query on every
 * refetch, and an OperationException raised for that would surface as a rendered error instead of
 * the refetch it actually was, so the original DOMException is rethrown untouched.
 */
test('an aborted request is rethrown as itself rather than wrapped', function () {
    expect(emitUtilsFor())
        ->toContain('if (result.type === "CLIENT_ERROR") {')
        ->toContain('throw result.cause;');
});

test('the utils carry no knowledge of any specific client implementation', function () {
    // Directive guards belong to the client that emits the directives, not to the shared utils.
    expect(emitUtilsFor())
        ->not->toContain('__client')
        ->not->toContain('operations-spa')
        ->not->toContain('ClientToast')
        ->not->toContain('ClientRedirect');
});

/**
 * The guard is a public util, not transport-private: what the transport itself trusts, an
 * application can reuse on any payload claiming to be an envelope. CLIENT_ERROR has no entry on
 * purpose — that branch is minted by the client itself, so a body claiming it is never believed.
 */
test('isValidEnvelop only believes what the server can actually send', function () {
    expect(emitUtilsFor())
        ->toContain('export function isValidEnvelop(value: unknown): value is Result<unknown, string> {')
        ->toContain('const SERVER_ERROR_CODES = {')
        ->toContain("&& typeof code === 'number'")
        ->toContain('&& SERVER_ERROR_CODES[type as keyof typeof SERVER_ERROR_CODES] === code;')
        ->not->toContain('CLIENT_ERROR: 0');
});

/**
 * The map in the emitted guard and the ErrorType enum are two literals, so this is the guard
 * against drift between them: a case added to the enum and forgotten in the heredoc would make the
 * client refuse a category the server really answers with.
 */
test('the guard map mirrors the ErrorType catalogue', function () {
    $utils = emitUtilsFor();

    foreach (ErrorType::cases() as $case) {
        expect($utils)->toContain("{$case->name}: {$case->value},");
    }
});

test('imports the envelope as types and the exception as a value', function () {
    // './lib/x' is what an emitter writes — the way a module at the output root reaches it. utils.ts
    // lands inside lib/ and reaches a sibling directly, which the orchestrator resolves; that form
    // is pinned in TypescriptServerCodeGeneratorTest.
    //
    // OperationException is constructed, so a type only import would leave `new OperationException(...)`
    // referencing nothing at runtime.
    expect(emitUtilsFor())
        ->toContain("import {OperationException} from './lib/OperationException';")
        ->toContain("import type {Result, Success} from './lib/types';");
});
