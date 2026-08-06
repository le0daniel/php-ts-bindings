<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

interface OperationKeyGenerator
{
    public function generateKey(string $namespace, string $name): string;
}
