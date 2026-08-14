<?php

declare(strict_types=1);

namespace Tests\Unit\Parser\Data\Stubs;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;

#[Castable(ObjectCastStrategy::NEVER)]
final class ExplicitNeverCasting
{
    public string $name;
}
