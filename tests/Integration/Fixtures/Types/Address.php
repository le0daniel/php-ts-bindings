<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Optional;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;

/**
 * ASSIGN_PROPERTIES strategy: hydrated by property assignment, no constructor. The Optional
 * company keeps its null default when the key is absent from the input. Property names are
 * alphabetical by design, so declaration order matches the canonical serialized key order.
 */
#[Castable(ObjectCastStrategy::ASSIGN_PROPERTIES)]
final class Address
{
    public string $city;

    #[Optional]
    public ?string $company = null;

    public string $street;

    public string $zip;
}
