<?php

declare(strict_types=1);

namespace Tests\Unit\Server\Errors\Mocks;

use Exception;
use Le0daniel\PhpTsBindings\Contracts\Attributes\ExposeAs;

/**
 * An invalid exposure: the bare attribute defaults to DOMAIN_ERROR, which requires a name.
 */
#[ExposeAs]
final class InvalidExposeAsException extends Exception
{
}
