<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks\TsOutput\Types;

use Exception;
use Le0daniel\PhpTsBindings\Contracts\Attributes\ExposeAs;

/**
 * Declared with #[Throws] and exposed, so it becomes one branch of the operation's DOMAIN_ERROR.
 */
#[ExposeAs('account_locked')]
final class AccountLockedException extends Exception
{
}
