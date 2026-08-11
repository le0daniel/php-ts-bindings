<?php

declare(strict_types=1);

namespace Tests\Unit\Server\Errors\Mocks;

use Exception;
use Le0daniel\PhpTsBindings\Contracts\Attributes\ExposeAs;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;

/**
 * A valid non-domain exposure: the type comes from the class, no name is involved.
 */
#[ExposeAs(ErrorType::NOT_FOUND)]
final class NotFoundExposedException extends Exception
{
}
