<?php declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;

/**
 * Declares the codegen metadata once for every int backed id. Implementors inherit both attributes
 * and derive their own brand and alias from their own class name.
 */
#[Brand]
#[Named]
interface IntId extends IntValueObject
{
}
