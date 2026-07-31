<?php declare(strict_types=1);

namespace Tests\Unit\Adapters\Laravel;

use Le0daniel\PhpTsBindings\Adapters\Laravel\Utils\ArtisanOptions;

test('expands repeated and comma separated options into one deduped list', function () {
    expect(ArtisanOptions::expandOptionsArrayCommaSeparated(['a,b', 'c']))->toBe(['a', 'b', 'c'])
        ->and(ArtisanOptions::expandOptionsArrayCommaSeparated('a, b ,a'))->toBe(['a', 'b'])
        ->and(ArtisanOptions::expandOptionsArrayCommaSeparated(null))->toBe([])
        ->and(ArtisanOptions::expandOptionsArrayCommaSeparated(true))->toBe([]);
});

test('asString only accepts a single string', function () {
    expect(ArtisanOptions::asString('value'))->toBe('value')
        ->and(ArtisanOptions::asString(null))->toBeNull()
        ->and(ArtisanOptions::asString(true))->toBeNull()
        ->and(ArtisanOptions::asString(['a']))->toBeNull();
});

/**
 * Command::hasOption() is true whenever an option is *declared*, so it cannot answer "did the user
 * pass this?". Absence has to be read off the value, which is what makes the fallback reachable.
 */
test('an absent option falls back to the configured default', function () {
    expect(ArtisanOptions::asPositiveInt(null, 10))->toBe(10)
        ->and(ArtisanOptions::asPositiveInt(null, '10'))->toBe(10);
});

test('a passed option overrides the configured default', function () {
    expect(ArtisanOptions::asPositiveInt('4', 10))->toBe(4)
        ->and(ArtisanOptions::asPositiveInt(4, 10))->toBe(4);
});

test('anything that is not a positive integer is rejected rather than coerced', function (mixed $option, mixed $fallback) {
    expect(ArtisanOptions::asPositiveInt($option, $fallback))->toBeNull();
})->with([
    'both absent' => [null, null],
    'zero' => ['0', null],
    'negative' => ['-1', null],
    'not numeric' => ['abc', null],
    'float' => ['1.5', null],
    'a flag rather than a value' => [true, null],
    'repeated option' => [['4'], null],
    'unusable fallback' => [null, 'abc'],
    'zero fallback' => [null, 0],
]);
