<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;

/**
 * Defines how an object can be cast when used during parsing.
 * Only classes that have this attribute can be cast.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Castable
{
    public function __construct(
        public ?ObjectCastStrategy $strategy = null,
    )
    {
    }
}