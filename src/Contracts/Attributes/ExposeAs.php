<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;

/**
 * Marks an exception as Exposable to the client, under the given type name.
 *
 * This is the exception's own name, used by every operation that declares it via #[Throws]. A
 * #[Throws(..., as: ...)] naming it at the declaration site overrides this one.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ExposeAs
{
    public function __construct(
        public string $type
    ) {
    }
}
