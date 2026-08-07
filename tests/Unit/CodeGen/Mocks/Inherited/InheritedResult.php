<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks\Inherited;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;

#[Castable]
final class InheritedResult
{
    public string $label;
}
