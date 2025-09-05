<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;

interface Constraint extends ExportableToPhpCode
{
    public function validate(mixed $value, ExecutionContext $context): bool;
}