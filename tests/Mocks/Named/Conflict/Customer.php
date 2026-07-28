<?php declare(strict_types=1);

namespace Tests\Mocks\Named\Conflict;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;

/**
 * Same short name as Tests\Mocks\Named\Customer but a different shape: using both in one run
 * must fail with a conflicting alias error.
 */
#[Named]
#[Castable]
final class Customer
{
    public int $age;
}
