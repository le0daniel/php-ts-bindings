<?php

declare(strict_types=1);

use Le0daniel\PhpTsBindings\Reflection\PropertiesReflector;
use Tests\Unit\Reflection\Mocks\PropertyVisibilityShowcase;

test('classifies property accessibility from public scope', function (string $property, bool $writable, bool $readable) {
    $reflection = new ReflectionProperty(PropertyVisibilityShowcase::class, $property);

    expect(PropertiesReflector::isWritableFromPublicScope($reflection))->toBe($writable)
        ->and(PropertiesReflector::isReadableFromPublicScope($reflection))->toBe($readable);
})->with([
    'plain public' => ['plain', true, true],
    'protected' => ['protectedProp', false, false],
    'private' => ['privateProp', false, false],
    'readonly' => ['readonlyProp', false, true],
    'private(set)' => ['privateSet', false, true],
    'protected(set)' => ['protectedSet', false, true],
    'virtual get-only hook' => ['virtualGetOnly', false, true],
    'virtual set-only hook' => ['virtualSetOnly', true, false],
    'virtual get and set hooks' => ['virtualGetSet', true, true],
    'backed set hook' => ['backedWithSetHook', true, true],
]);
