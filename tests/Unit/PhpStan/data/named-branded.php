<?php

declare(strict_types=1);

namespace Tests\Unit\PhpStan\Data\NamedBranded;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;

use function PHPStan\Testing\assertType;

/**
 * The import at the top is the precedence proof: exactly the files using the #[Named] attribute
 * import a class called Named, and the docblock utility must still resolve through the extension
 * instead of the class. The class itself stays reachable as a native type.
 */
function theImportedClassStaysUsableNatively(Named $attribute): void
{
    assertType(Named::class, $attribute);
}

/**
 * @param  Named<"AccountId", string>  $v
 */
function named(string $v): void
{
    assertType('string', $v);
}

/**
 * @param  Branded<"accountId", string>  $v
 */
function branded(string $v): void
{
    assertType('string', $v);
}

/**
 * @param  Branded<"accountId", Named<"AccountId", string>>  $v
 */
function nested(string $v): void
{
    assertType('string', $v);
}

/**
 * @param  Named<"Coords", array{lat: float, lng: float}>  $v
 */
function namedShape(array $v): void
{
    assertType('array{lat: float, lng: float}', $v);
}

/**
 * @param  Branded<"userId", int>|null  $v
 */
function brandedInUnion(?int $v): void
{
    assertType('int|null', $v);
}

/**
 * @param  Named<"X", non-empty-string>  $v
 */
function namedConstrained(string $v): void
{
    assertType('non-empty-string', $v);
}
