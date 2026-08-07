<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks;

use Tests\Unit\CodeGen\Mocks\Inherited\BaseOperations;

/**
 * Registers an operation it does not declare. Nothing here imports InheritedResult, on purpose.
 */
final class InheritingOperations extends BaseOperations
{
}
