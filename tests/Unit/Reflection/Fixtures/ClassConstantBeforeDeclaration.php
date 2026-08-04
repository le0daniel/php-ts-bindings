<?php declare(strict_types=1);

namespace Tests\Unit\Reflection\Fixtures;

use DateTimeImmutable;

/**
 * `Foo::class` tokenizes as T_CLASS, so a naive scan for the first T_CLASS finds this line rather
 * than the declaration below it. The next significant token here is a bare identifier, which is
 * what defeats the "is the next token a T_STRING" guard.
 */
const SOME_MARKER = DateTimeImmutable::class . PHP_EOL;

final class ClassConstantBeforeDeclaration
{
    public string $name = 'real';
}
