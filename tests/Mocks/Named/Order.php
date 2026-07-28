<?php declare(strict_types=1);

namespace Tests\Mocks\Named;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;
use Tests\Mocks\ValueObjects\UserId;

/**
 * A named type containing another named type, so alias emission has to recurse.
 */
#[Named]
#[Castable]
final class Order
{
    public Customer $customer;
    public UserId $id;
}
