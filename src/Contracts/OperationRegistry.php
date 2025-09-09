<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;

interface OperationRegistry
{

    /**
     * @param OperationType $type
     * @param string $fullyQualifiedKey
     * @return bool
     */
    public function has(OperationType $type, string $fullyQualifiedKey): bool;

    /**
     * @param OperationType $type
     * @param string $fullyQualifiedKey
     * @return Operation
     */
    public function get(OperationType $type, string $fullyQualifiedKey): Operation;

    /**
     * @return array<string, Operation>
     */
    public function all(): array;
}