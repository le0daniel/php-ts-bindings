<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

use Exception;

final class Failure extends Exception
{
    public function __construct(
        public Issues $issues,
    )
    {
        parent::__construct('Validation failed.', 422);
    }
}