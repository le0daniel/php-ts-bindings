<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks\TsOutput\Types;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;

/**
 * A named type whose properties carry refinements: they are enforced on the way in and disappear
 * from the TypeScript, so the one alias describes both directions.
 */
#[Named]
#[Castable]
final class Money
{
    /** @var positive-int */
    public int $amount;

    /** @var non-empty-uppercase-string */
    public string $currency;
}
