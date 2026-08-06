<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;

/**
 * A second brand carrying interface, used to build the ambiguous case together with IntId.
 */
#[Brand]
interface AlsoBranded extends IntValueObject
{
}
