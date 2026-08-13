<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;

interface OperationRegistry
{
    public function has(OperationType $type, string $key): bool;

    public function get(OperationType $type, string $key): Operation;

    /**
     * @return array<string, Operation>
     */
    public function all(): array;
}
