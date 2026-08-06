<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks\TsOutput\Types;

use Exception;

/**
 * Declared with #[Throws] but never marked #[ExposeAs]: it reaches the client as a plain 500 and
 * must not appear in the generated error union.
 */
final class ProvisioningException extends Exception
{
}
