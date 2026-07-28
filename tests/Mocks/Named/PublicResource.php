<?php declare(strict_types=1);

namespace Tests\Mocks\Named;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;

/**
 * Interfaces are output only (uncastable as input); the default io of OUTPUT names exactly the
 * direction they exist in.
 */
#[Named]
interface PublicResource
{
    public string $url {
        get;
    }
}
