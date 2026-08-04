<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks\TsOutput\Types;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Optional;

/**
 * Castable but not named, so its shape is inlined into the operation's input type. The optional
 * property is what the generated `?:` comes from.
 */
#[Castable]
final class AccountFilter
{
    public string $term;

    #[Optional]
    public ?Availability $availability;
}
