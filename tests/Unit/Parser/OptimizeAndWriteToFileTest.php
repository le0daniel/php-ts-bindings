<?php declare(strict_types=1);

namespace Tests\Unit\Parser;

use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Parser\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\Registry\CachedTypeRegistry;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Tests\Unit\Parser\Data\Stubs\AccountData;

/**
 * optimizeAndWriteToFile() is the API the README teaches, and it was the only path through the
 * optimizer with no coverage: everything else calls generateOptimizedCode() directly and never
 * touches the file the documentation tells users to require.
 */
beforeEach(function () {
    $this->file = sys_get_temp_dir() . '/php-ts-bindings-asts-' . getmypid() . '.php';
});

afterEach(function () {
    if (is_file($this->file)) {
        unlink($this->file);
    }
});

test('the written file round-trips: optimize, require, execute', function () {
    $parser = new TypeParser();

    new ASTOptimizer()->optimizeAndWriteToFile($this->file, [
        'account@output' => $parser->parse('\\' . AccountData::class),
        'scalar@input' => $parser->parse('int'),
    ]);

    $registry = require $this->file;
    expect($registry)->toBeInstanceOf(CachedTypeRegistry::class);

    $executor = new SchemaExecutor();
    expect($executor->parse($registry->get('scalar@input'), 42))->toBeSuccess()
        ->and($executor->parse($registry->get('scalar@input'), 'nope'))->toBeFailure();

    // A schema loaded from the cache behaves exactly like the one it was built from.
    $serialized = $executor->serialize($registry->get('account@output'), new AccountData(1, 'Ada'));
    expect($serialized)->toBeSuccess()
        ->and($serialized->value->id)->toBe(1)
        ->and($serialized->value->name)->toBe('Ada');
});

test('the written file is valid PHP that returns a registry', function () {
    new ASTOptimizer()->optimizeAndWriteToFile($this->file, [
        'scalar@input' => new TypeParser()->parse('string'),
    ]);

    $contents = file_get_contents($this->file);
    expect($contents)->toStartWith('<?php declare(strict_types=1);')
        ->and($contents)->toContain('return new ');
});

// Byte-for-byte reproducibility is what lets a build compare a regenerated cache against the
// committed one to decide whether it is stale.
test('writing the same schemas twice produces identical bytes', function () {
    $second = $this->file . '.second';

    foreach ([$this->file, $second] as $path) {
        new ASTOptimizer()->optimizeAndWriteToFile($path, [
            'account@input' => new TypeParser()->parse('\\' . AccountData::class),
        ]);
    }

    expect(file_get_contents($this->file))->toBe(file_get_contents($second));
    unlink($second);
});
