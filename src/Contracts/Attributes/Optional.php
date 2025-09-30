<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;

/**
 * Marks a property as Optional from an object. As PHP does only support
 * distinct values, by default, it will cast to NULL. Provide another value
 * if the property is undefined.
 */
#[Attribute(Attribute::TARGET_PROPERTY| Attribute::TARGET_PARAMETER)]
final readonly class Optional
{
}