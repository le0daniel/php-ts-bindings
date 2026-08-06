<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperationClientBindings;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperations;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperationsSpaClient;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitQueryKey;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTanstackQuery;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypeMap;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypes;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypeUtils;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptServerCodeGenerator;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\PlainlyExposedKeyGenerator;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedOperationRegistry;
use Le0daniel\PhpTsBindings\Server\Server;
use Tests\Unit\CodeGen\Mocks\UserOperations;

/**
 * @param  list<object>  $generators
 * @return list<string>
 */
function classesOf(array $generators): array
{
    return array_map(fn (object $generator): string => $generator::class, $generators);
}

/**
 * @param  string|\Closure(TypedOperation): string  $naming
 */
function usersModuleFor(string|\Closure $naming): string
{
    $server = new Server(
        EagerlyLoadedOperationRegistry::withClasses(
            [UserOperations::class],
            keyGenerator: new PlainlyExposedKeyGenerator(),
        ),
    );

    $files = new TypescriptServerCodeGenerator(
        CodeGenerators::fromDefaults($naming),
    )->generate($server, new ServerMetadata('/query/{fqn}', '/command/{fqn}'));

    return $files['users.ts']->toString();
}

test('defaults are the five on-by-default generators, in declaration order', function () {
    expect(classesOf(CodeGenerators::fromDefaults('name')))->toBe([
        EmitTypes::class,
        EmitOperationClientBindings::class,
        EmitTypeUtils::class,
        EmitOperationsSpaClient::class,
        EmitOperations::class,
    ]);
});

test('with adds an opt-in generator in declaration order, not append order', function () {
    expect(classesOf(CodeGenerators::fromDefaults('name', with: ['tanstack-query', 'type-map'])))->toBe([
        EmitTypes::class,
        EmitOperationClientBindings::class,
        EmitTypeUtils::class,
        EmitOperationsSpaClient::class,
        EmitOperations::class,
        EmitTypeMap::class,
        EmitTanstackQuery::class,
    ]);
});

test('with enables every opt-in generator', function () {
    expect(classesOf(CodeGenerators::fromDefaults('name', with: ['type-map', 'tanstack-query', 'query-key'])))
        ->toContain(EmitTypeMap::class, EmitTanstackQuery::class, EmitQueryKey::class)
        ->toHaveCount(8);
});

test('without drops a default generator', function () {
    expect(classesOf(CodeGenerators::fromDefaults('name', without: ['operations-spa'])))->toBe([
        EmitTypes::class,
        EmitOperationClientBindings::class,
        EmitTypeUtils::class,
        EmitOperations::class,
    ]);
});

test('with wins over without when a name appears in both', function () {
    expect(classesOf(CodeGenerators::fromDefaults('name', with: ['types'], without: ['types'])))
        ->toContain(EmitTypes::class);

    expect(classesOf(CodeGenerators::fromDefaults('name', with: ['type-map'], without: ['type-map'])))
        ->toContain(EmitTypeMap::class);
});

test('without an already off generator is a no-op', function () {
    expect(classesOf(CodeGenerators::fromDefaults('name', without: ['tanstack-query'])))
        ->toBe(classesOf(CodeGenerators::fromDefaults('name')));
});

test('an unknown name in with or without is ignored', function () {
    expect(classesOf(CodeGenerators::fromDefaults('name', with: ['nope'], without: ['also-nope'])))
        ->toBe(classesOf(CodeGenerators::fromDefaults('name')));
});

test('each naming mode names the generated function', function (string $mode, string $expected) {
    expect(usersModuleFor($mode))->toContain("export async function {$expected}(");
})->with([
    ['name', 'get'],
    ['fqn', 'usersGet'],
    ['operation-prefix', 'usersGet'],
    ['namespace-postfix', 'getUsers'],
]);

test('a closure is accepted in place of a naming mode', function () {
    $naming = fn (TypedOperation $operation): string => "do_{$operation->definition->name}";

    expect(usersModuleFor($naming))->toContain('export async function do_get(');
});

test('namingGenerator returns the closure a mode stands for', function () {
    expect(usersModuleFor(CodeGenerators::namingGenerator('fqn')))
        ->toContain('export async function usersGet(');
});
