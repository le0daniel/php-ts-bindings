<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;

/**
 * Marks a property or promoted parameter as absent-able in input, emitted as `key?:` in TypeScript.
 *
 * PHP has no "undefined", so the property needs somewhere to land when input omits it: either a
 * default value or a nullable type. A property with neither is rejected at parse time rather than
 * silently receiving null.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class Optional
{
}
