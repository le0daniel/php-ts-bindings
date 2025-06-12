<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

interface Constraint extends ExportableToPhpCode
{
    public function validate(mixed $value, ExecutionContext $context): bool;
}