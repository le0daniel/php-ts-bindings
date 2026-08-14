<?php

declare(strict_types=1);

namespace Tests\Mocks\Named;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;

#[Named('not a valid identifier')]
#[Castable]
final class InvalidlyNamed
{
    public string $value;
}
