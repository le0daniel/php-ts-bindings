<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;

#[Attribute(Attribute::TARGET_CLASS)]
final class Castable
{
    public function __construct(
        public ?ObjectCastStrategy $strategy = null,
    )
    {
    }
}