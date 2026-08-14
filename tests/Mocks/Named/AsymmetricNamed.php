<?php

declare(strict_types=1);

namespace Tests\Mocks\Named;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;

/**
 * Input ({secret:string;}) and output ({visible:string;}) differ, so the one alias #[Named] derives
 * cannot describe both and validation must reject it.
 */
#[Named]
#[Castable]
final class AsymmetricNamed
{
    public string $visible;

    public function __construct(string $secret)
    {
        $this->visible = strrev($secret);
    }
}
