<?php declare(strict_types=1);

namespace Tests\Mocks\Named;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;
use Tests\Mocks\ValueObjects\Email;

/**
 * A named type containing a branded value object.
 */
#[Named]
#[Castable]
final class Customer
{
    public Email $email;
    public string $name;
}
