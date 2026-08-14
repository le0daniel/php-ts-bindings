<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks\TsOutput\Types;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;

/**
 * A named enum, used in both directions and by more than one namespace, so its alias has to be
 * declared once and imported by every module that mentions it.
 */
#[Named]
enum Availability
{
    case IN_STOCK;
    case SOLD_OUT;
    case PREORDER;
}
