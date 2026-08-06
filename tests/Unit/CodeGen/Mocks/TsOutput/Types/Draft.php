<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks\TsOutput\Types;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;

/**
 * Built from a title, read back as a slug: two shapes, so the naming closure gives each direction
 * its own alias (DraftInput and Draft).
 */
#[Named(name: AliasNaming::perDirection(...))]
#[Castable]
final class Draft
{
    public string $slug;

    public function __construct(string $title)
    {
        $this->slug = strtolower(str_replace(' ', '-', $title));
    }
}
