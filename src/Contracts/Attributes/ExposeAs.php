<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;

/**
 * Marks an exception as Exposable to the client.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ExposeAs
{
    public function __construct(
        public string $type
    )
    {
    }
}