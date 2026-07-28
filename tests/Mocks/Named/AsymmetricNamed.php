<?php declare(strict_types=1);

namespace Tests\Mocks\Named;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;
use Le0daniel\PhpTsBindings\Typescript\Data\IO;

/**
 * Input ({secret:string;}) and output ({visible:string;}) differ, so naming it for IO::BOTH must
 * fail generation with a conflicting alias error.
 */
#[Named(io: IO::BOTH)]
#[Castable]
final class AsymmetricNamed
{
    public string $visible;

    public function __construct(string $secret)
    {
        $this->visible = strrev($secret);
    }
}
