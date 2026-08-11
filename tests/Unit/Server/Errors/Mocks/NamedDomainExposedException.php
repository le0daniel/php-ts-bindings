<?php

declare(strict_types=1);

namespace Tests\Unit\Server\Errors\Mocks;

use Exception;
use Le0daniel\PhpTsBindings\Contracts\Attributes\ExposeAs;

/**
 * A valid domain exposure: naming it defaults the type to DOMAIN_ERROR.
 */
#[ExposeAs(name: 'exposed_domain_name')]
final class NamedDomainExposedException extends Exception
{
}
