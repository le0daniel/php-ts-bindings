<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperationClientBindings;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypes;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
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
    $emitter->setDependencies([EmitTypes::class => new EmitTypes()]);

    return $emitter->emitFiles(
        [],
        new ServerMetadata('/query/{fqn}', '/command/{fqn}'),
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
        './lib/types' => ['values' => [], 'types' => ['Result', 'WithClientDirectives']],
    ]],
    'DefaultClient' => ['DefaultClient', [
        './lib/OperationClient' => ['values' => [], 'types' => ['OperationClient', 'OperationOptions']],
        './lib/types' => ['values' => [], 'types' => ['Failure', 'Result', 'Success', 'WithClientDirectives']],
    ]],
    'OperationException' => ['OperationException', [
        './lib/types' => ['values' => [], 'types' => ['Failure']],
    ]],
    // DefaultClient and OperationException are constructed, so they are value imports; a type only
    // import of either would leave `new DefaultClient(...)` referencing nothing at runtime.
    'bindings' => ['bindings', [
        './lib/DefaultClient' => ['values' => ['DefaultClient'], 'types' => []],
        './lib/OperationClient' => ['values' => [], 'types' => ['OperationClient', 'OperationOptions']],
        './lib/OperationException' => ['values' => ['OperationException'], 'types' => []],
        './lib/types' => ['values' => [], 'types' => ['Result', 'Success', 'WithClientDirectives']],
    ]],
]);

test('imports nothing its body does not reference', function () {
    $unused = [];
    foreach (bindingFiles() as $name => $file) {
        foreach ($file->imports as $import) {
            foreach ([...$import->values, ...$import->types] as $imported) {
                if (!str_contains($file->code, $imported)) {
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

test('every import names a file this generator emits, or the types file', function () {
    $emitted = array_keys(bindingFiles());

    $unknown = [];
    foreach (bindingFiles() as $file) {
        foreach ($file->imports as $import) {
            $name = str_replace('./lib/', '', $import->from);
            if ($name !== 'types' && !in_array($name, $emitted, true)) {
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
