<?php

declare(strict_types=1);

namespace Tests\Mocks\Errors;

use Exception;
use Le0daniel\PhpTsBindings\Contracts\Attributes\ExposeAs;

#[ExposeAs(name: 'domain_failure')]
final class ExposedDomainException extends Exception
{
}
