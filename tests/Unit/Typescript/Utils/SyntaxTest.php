<?php declare(strict_types=1);

namespace Tests\Unit\Typescript\Utils;

use Le0daniel\PhpTsBindings\Typescript\Utils\Syntax;

test('object key', function () {
    expect(Syntax::objectKey('foo'))->toBe('foo');
    expect(Syntax::objectKey('foo', true))->toBe('foo?');
    expect(Syntax::objectKey('foo a'))->toBe('"foo a"');
    expect(Syntax::objectKey('foo a', true))->toBe('"foo a"?');
});
