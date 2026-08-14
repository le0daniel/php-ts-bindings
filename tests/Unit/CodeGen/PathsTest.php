<?php

declare(strict_types=1);

use Le0daniel\PhpTsBindings\CodeGen\Utils\Paths;

/**
 * Both halves of one rule: a lib file is always named the way a module at the output root reaches
 * it, and fromInsideLib() is what a file that landed in lib/ itself has to write instead.
 */
test('names a lib file the way a module at the output root reaches it', function () {
    expect(Paths::libImport('types'))->toBe('./lib/types');
});

test('names the same lib file the way a sibling inside lib reaches it', function () {
    expect(Paths::fromInsideLib(Paths::libImport('types')))->toBe('./types')
        ->and(Paths::fromInsideLib(Paths::libImport('OperationClient')))->toBe('./OperationClient');
});

test('leaves a specifier that names no lib file alone', function (string $specifier) {
    expect(Paths::fromInsideLib($specifier))->toBe($specifier);
})->with([
    'a package' => ['@tanstack/react-query'],
    'a module already reached directly' => ['./types'],
    'a name that merely starts with lib' => ['./library'],
]);
