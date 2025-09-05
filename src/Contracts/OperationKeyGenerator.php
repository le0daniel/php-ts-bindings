<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

use Le0daniel\PhpTsBindings\Server\Data\Definition;

interface OperationKeyGenerator
{

    public function generateKey(Definition $definition): string;

}