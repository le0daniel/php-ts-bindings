<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\Utils\OutputDirectory;

/**
 * The generated TypeScript in tests/ts-output/generated was compiled with `tsc --noEmit` when it
 * was written, together with the hand-written client in tests/ts-output/src. This test is the other
 * half of that guarantee: the generators still produce exactly those bytes, so the code the
 * compiler accepted is the code they emit today.
 *
 * It deliberately runs no compiler. Regenerating and typechecking is `composer codegen:fixture`,
 * which needs node; verifying does not, so the PHP suite stays runnable on its own.
 */
test('the committed TypeScript fixture is what the generators produce', function () {
    $issues = OutputDirectory::verify(
        TsOutputFixture::directory(),
        TsOutputFixture::generate(),
    );

    expect($issues)->toBe([], implode(PHP_EOL, [
        'The generated TypeScript fixture is out of date:',
        ...array_map(fn(string $issue): string => "  - {$issue}", $issues),
        '',
        'Run `composer codegen:fixture` to regenerate it and verify it still compiles.',
    ]));
});

test('the fixture covers every generated file kind', function () {
    expect(array_keys(TsOutputFixture::generate()))
        ->toContain('lib/types.ts')
        ->toContain('lib/OperationClient.ts')
        ->toContain('lib/DefaultClient.ts')
        ->toContain('lib/OperationException.ts')
        ->toContain('lib/bindings.ts')
        ->toContain('lib/utils.ts')
        ->toContain('lib/client-operations-spa.ts')
        ->toContain('lib/type-map.ts')
        // One module per namespace, so cross-module imports of the shared aliases are compiled too.
        ->toContain('accounts.ts')
        ->toContain('catalog.ts')
        ->toContain('shapes.ts');
});
