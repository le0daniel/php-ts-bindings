<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Validators;

interface Constraint
{
    public function validate(mixed $value, $context): bool;
}