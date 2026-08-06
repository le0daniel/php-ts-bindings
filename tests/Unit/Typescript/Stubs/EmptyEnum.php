<?php

declare(strict_types=1);

namespace Tests\Unit\Typescript\Stubs;

/**
 * An enum with no cases has no TypeScript representation: the union of nothing is not a type.
 */
enum EmptyEnum
{
}
