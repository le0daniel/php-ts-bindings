<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\Exceptions\CodeGenException;
use Le0daniel\PhpTsBindings\CodeGen\Utils\OutputDirectory;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;

/**
 * write() removes what it finds before writing, so what it considers "its own" is the whole safety
 * story. The marker on the first line is that answer.
 */
function outputDirectory(): string
{
    $directory = sys_get_temp_dir().'/php-ts-bindings-output-'.bin2hex(random_bytes(6));
    mkdir($directory.'/lib', 0777, true);

    return $directory;
}

function removeDirectory(string $directory): void
{
    foreach (glob("{$directory}/{lib/,}*", GLOB_BRACE) ?: [] as $path) {
        is_dir($path) ? removeDirectory($path) : unlink($path);
    }
    @rmdir($directory);
}

test('a hand written file survives a regeneration', function () {
    $directory = outputDirectory();
    file_put_contents("{$directory}/handwritten.ts", "export const mine = 1;\n");

    OutputDirectory::write($directory, ['users.ts' => new TypescriptFile('export type A = 1;')]);

    expect(file_get_contents("{$directory}/handwritten.ts"))->toBe("export const mine = 1;\n")
        ->and(file_exists("{$directory}/users.ts"))->toBeTrue();

    removeDirectory($directory);
});

test('a module left behind by a removed operation is pruned', function () {
    $directory = outputDirectory();
    OutputDirectory::write($directory, [
        'users.ts' => new TypescriptFile('export type A = 1;'),
        'orders.ts' => new TypescriptFile('export type B = 2;'),
    ]);

    OutputDirectory::write($directory, ['users.ts' => new TypescriptFile('export type A = 1;')]);

    expect(file_exists("{$directory}/users.ts"))->toBeTrue()
        ->and(file_exists("{$directory}/orders.ts"))->toBeFalse();

    removeDirectory($directory);
});

test('overwriting an unmarked file with a generated module is refused', function () {
    // The one case the marker cannot recover from: a hand written module whose name collides with
    // one the generators are about to write.
    $directory = outputDirectory();
    file_put_contents("{$directory}/users.ts", "export const mine = 1;\n");

    expect(fn () => OutputDirectory::write($directory, ['users.ts' => new TypescriptFile('export type A = 1;')]))
        ->toThrow(CodeGenException::class, 'Refusing to overwrite users.ts');

    // Refused before anything was touched.
    expect(file_get_contents("{$directory}/users.ts"))->toBe("export const mine = 1;\n");

    removeDirectory($directory);
});

test('verify ignores a file this library did not write', function () {
    $directory = outputDirectory();
    $files = ['users.ts' => new TypescriptFile('export type A = 1;')];
    OutputDirectory::write($directory, $files);
    file_put_contents("{$directory}/handwritten.ts", "export const mine = 1;\n");

    expect(OutputDirectory::verify($directory, $files))->toBe([]);

    removeDirectory($directory);
});

test('verify still reports a stale generated module and a changed one', function () {
    $directory = outputDirectory();
    OutputDirectory::write($directory, [
        'users.ts' => new TypescriptFile('export type A = 1;'),
        'orders.ts' => new TypescriptFile('export type B = 2;'),
    ]);

    $issues = OutputDirectory::verify($directory, [
        'users.ts' => new TypescriptFile('export type A = 2;'),
    ]);

    expect($issues)->toBe([
        'File orders.ts is not generated anymore and should be deleted.',
        'File users.ts does not match the generated output.',
    ]);

    removeDirectory($directory);
});
