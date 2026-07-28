<?php declare(strict_types=1);

namespace Tests\Mocks\Named;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;

#[Brand('not a valid identifier')]
#[Castable]
final class InvalidlyBranded
{
    public string $value;
}
