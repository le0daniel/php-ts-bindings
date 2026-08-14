<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

use Le0daniel\PhpTsBindings\Contracts\ValueObjects\StringValueObject;

/**
 * An attribute free interface: implementors must stay bare nodes.
 */
interface PlainContract extends StringValueObject
{
}
