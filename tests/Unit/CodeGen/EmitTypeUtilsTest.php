<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypeUtils;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\ToastType;
use Le0daniel\PhpTsBindings\Typescript\Data\IO;
use Le0daniel\PhpTsBindings\Typescript\Data\TypeRegistry;
use Le0daniel\PhpTsBindings\Typescript\Data\TypeScript;
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
    $registry = new TypeRegistry();
    $input = $generator->toTypescript($operation->inputNode(), IO::INPUT, $registry);
    $output = $generator->toTypescript($operation->outputNode(), IO::OUTPUT, $registry);

    $files = new EmitTypeUtils()->emitFiles(
        [new TypedOperation($input, $output, TypeScript::fromRawString(''), $operation)],
        new ServerMetadata('/query/{fqn}', '/command/{fqn}'),
        $registry,
    );

    return $files['utils']->toString();
}

test('query namespaces are emitted as a literal union', function () {
    expect(emitUtilsFor(namespace: 'orders'))->toContain("type QueryNamespaces = 'orders';");
});

test('the toast type list the guard checks against is derived from the PHP enum', function () {
    $utils = emitUtilsFor();

    $cases = implode(', ', array_map(
        fn(ToastType $type): string => "'{$type->value}'",
        ToastType::cases(),
    ));

    expect($utils)->toContain("const TOAST_TYPES = [{$cases}] as const;");
});

test('the directive guard verifies every directive it narrows, not just the discriminator', function () {
    $utils = emitUtilsFor();

    // A guard that only checks __client.type would happily narrow a payload from a server
    // still emitting the old {type: 'soft'|'hard'} redirect.
    expect($utils)
        ->toContain('export function isClientRedirect(value: unknown): value is ClientRedirect')
        ->toContain('export function isClientToast(value: unknown): value is ClientToast')
        ->toContain("typeof redirect.reload === 'boolean'")
        ->toContain('isClientRedirect(directives.redirect)')
        ->toContain('isArrayOf(directives.toasts, isClientToast)')
        ->toContain('isArrayOf(directives.invalidations, isClientInvalidation)');
});

test('the guard imports the named directive types instead of restating their shape', function () {
    expect(emitUtilsFor())
        ->toContain('import type {ClientDirectives, ClientRedirect, ClientToast, SPAClientDirectives, WithClientDirectives} from "./types";');
});
