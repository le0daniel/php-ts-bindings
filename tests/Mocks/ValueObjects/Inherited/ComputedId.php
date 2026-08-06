<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;

/**
 * The naming closure earns its keep here: an inherited declaration cannot carry a fixed name, but
 * it can carry a rule that each implementor runs against its own class name.
 */
#[Brand(name: Naming::prefixedBrand(...))]
#[Named(name: Naming::suffixedAlias(...))]
interface ComputedId extends IntValueObject
{
}
