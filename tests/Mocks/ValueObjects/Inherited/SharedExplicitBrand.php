<?php declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;

/**
 * Invalid on purpose: an explicit name on a shared declaration would give every implementor the
 * identical brand, collapsing them into one mutually assignable TypeScript type.
 */
#[Brand('sharedId')]
interface SharedExplicitBrand extends IntValueObject
{
}
