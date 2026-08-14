<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

/**
 * Exists solely to host a phpstan-type alias that another fixture imports with
 * "@phpstan-import-type PriceRange from CatalogShared as Range".
 *
 * @phpstan-type PriceRange array{max: int, min: int}
 */
final class CatalogShared
{
}
