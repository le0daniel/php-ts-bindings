<?php declare(strict_types=1);

namespace Tests\Mocks\Named;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;

/**
 * A brand on a whole class: the object shape is intersected inline, no alias is declared.
 */
#[Brand('payload')]
#[Castable]
final class BrandedPayload
{
    public string $value;
}
