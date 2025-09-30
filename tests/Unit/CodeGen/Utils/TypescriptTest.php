<?php

namespace Tests\Unit\CodeGen\Utils;

use Le0daniel\PhpTsBindings\CodeGen\Utils\Typescript;

test('object key', function () {
    expect(Typescript::objectKey('foo'))->toBe('foo');
    expect(Typescript::objectKey('foo', true))->toBe('foo?');
    expect(Typescript::objectKey('foo a'))->toBe('"foo a"');
    expect(Typescript::objectKey('foo a', true))->toBe('"foo a"?');
});