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
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Typescript\Data\Typescript;
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
        [new TypedOperation($input, $output, Typescript::fromRawString(''), $operation)],
        new ServerMetadata('/query/{fqn}', '/command/{fqn}'),
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
        ->toContain('export function throwOnFailure<const T>(result: Result<T, any>): asserts result is Success<T>')
        ->toContain('throw new OperationException(result);');
});

test('the utils carry no knowledge of any specific client implementation', function () {
    // Directive guards belong to the client that emits the directives, not to the shared utils.
    expect(emitUtilsFor())
        ->not->toContain('__client')
        ->not->toContain('operations-spa')
        ->not->toContain('ClientToast')
        ->not->toContain('ClientRedirect');
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
