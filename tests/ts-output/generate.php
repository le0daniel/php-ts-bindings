<?php declare(strict_types=1);

/**
 * Writes the TypeScript fixture that tests/Unit/CodeGen/TsOutputFixtureTest.php verifies.
 *
 * Run it through `composer codegen:fixture`, which also hands the result to the TypeScript
 * compiler — the fixture is only worth committing once tsc has accepted it.
 */

use Le0daniel\PhpTsBindings\CodeGen\Utils\OutputDirectory;
use Tests\Unit\CodeGen\TsOutputFixture;

require __DIR__ . '/../../vendor/autoload.php';

$directory = TsOutputFixture::directory();
$files = TsOutputFixture::generate();

OutputDirectory::write($directory, $files);

$count = count($files);
echo "Wrote {$count} file(s) to tests/ts-output/generated:", PHP_EOL;
foreach (array_keys($files) as $fileName) {
    echo "  {$fileName}", PHP_EOL;
}
