<?php declare(strict_types=1);

namespace Tests\Unit\PhpStan;

use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class UtilitiesResolverTest extends TypeInferenceTestCase
{
    /**
     * @return iterable<mixed>
     */
    public static function dataFileAsserts(): iterable
    {
        // path to a file with actual asserts of expected types:
        yield from self::gatherAssertTypes(__DIR__ . '/data/types.php');
    }

    #[DataProvider('dataFileAsserts')]
    public function testFileAsserts(
        string $assertType,
        string $file,
        mixed  ...$args
    ): void
    {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    public static function getAdditionalConfigFiles(): array
    {
        // path to your project's phpstan.neon, or extension.neon in case of custom extension packages
        return [__DIR__ . '/../../../extension.neon'];
    }
}