<?php

declare(strict_types=1);

namespace Tests\Unit\Parser\Data\Stubs;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;

#[Castable]
abstract class CastableAbstractClass
{
    public string $name;
}
