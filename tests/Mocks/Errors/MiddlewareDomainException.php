<?php

declare(strict_types=1);

namespace Tests\Mocks\Errors;

use Exception;
use Le0daniel\PhpTsBindings\Contracts\Attributes\ExposeAs;

#[ExposeAs('middleware_failure')]
final class MiddlewareDomainException extends Exception
{
}
