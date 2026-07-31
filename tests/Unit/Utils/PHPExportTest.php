<?php declare(strict_types=1);

namespace Tests\Unit\Utils;

use Le0daniel\PhpTsBindings\Parser\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

beforeEach(function () {
    $this->dir = sys_get_temp_dir() . '/php-ts-bindings-export-' . getmypid();
    if (!is_dir($this->dir)) {
        mkdir($this->dir, 0o777, true);
    }
});

afterEach(function () {
    foreach (glob($this->dir . '/*') ?: [] as $file) {
        unlink($file);
    }
    @rmdir($this->dir);
});

test('writes the file', function () {
    $target = $this->dir . '/out.php';
    PHPExport::writeFileAtomically($target, '<?php return 1;');

    expect(file_get_contents($target))->toBe('<?php return 1;');
});

test('replaces an existing file', function () {
    $target = $this->dir . '/out.php';
    file_put_contents($target, 'old');
    PHPExport::writeFileAtomically($target, 'new');

    expect(file_get_contents($target))->toBe('new');
});

/**
 * The cache writers require() what this produces while the application is serving traffic, so a
 * reader must see either the whole old file or the whole new one - never a partial write.
 */
test('leaves no temporary file behind', function () {
    $target = $this->dir . '/out.php';
    PHPExport::writeFileAtomically($target, str_repeat('x', 200_000));

    expect(glob($this->dir . '/*'))->toBe([$target]);
});

test('a reader never observes a partially written file', function () {
    $target = $this->dir . '/out.php';
    $complete = '<?php return ' . str_repeat('9', 500_000) . ';';

    PHPExport::writeFileAtomically($target, 'old');
    PHPExport::writeFileAtomically($target, $complete);

    // Whatever a concurrent reader saw, it was one of the two complete contents.
    expect(file_get_contents($target))->toBe($complete);
});

test('throws when the target directory is not writable', function () {
    expect(fn() => PHPExport::writeFileAtomically($this->dir . '/missing/out.php', 'x'))
        ->toThrow(ParserException::class, 'not a writable directory');
});
