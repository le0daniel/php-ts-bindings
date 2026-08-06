<?php

declare(strict_types=1);

namespace Tests\Mocks\Named;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;

/**
 * The same asymmetric shape as AsymmetricNamed, but the naming closure returns a distinct alias per
 * direction, so both shapes can be declared honestly: PerDirectionNamedInput and PerDirectionNamed.
 */
#[Named(name: AliasNaming::perDirection(...))]
#[Castable]
final class PerDirectionNamed
{
    public string $visible;

    public function __construct(string $secret)
    {
        $this->visible = strrev($secret);
    }
}
