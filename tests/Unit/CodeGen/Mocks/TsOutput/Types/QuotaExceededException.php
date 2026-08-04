<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks\TsOutput\Types;

use Exception;
use Le0daniel\PhpTsBindings\Contracts\Attributes\ExposeAs;

/**
 * The second exposed exception of one operation: the DOMAIN_ERROR details become a union, which is
 * what the client discriminates on.
 */
#[ExposeAs('quota_exceeded')]
final class QuotaExceededException extends Exception
{
}
