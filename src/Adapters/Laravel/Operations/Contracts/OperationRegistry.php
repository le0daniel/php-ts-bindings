<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Operations\Contracts;

use Le0daniel\PhpTsBindings\Adapters\Laravel\Operations\Data\Operation;

/**
 * @phpstan-type OperationType 'command'|'query'
 */
interface OperationRegistry
{

    /**
     * @param OperationType $type
     * @param string $fullyQualifiedKey
     * @return bool
     */
    public function has(string $type, string $fullyQualifiedKey): bool;

    /**
     * @param OperationType $type
     * @param string $fullyQualifiedKey
     * @return Operation
     */
    public function get(string $type, string $fullyQualifiedKey): Operation;

    /**
     * @return list<Operation>
     */
    public function all(): array;
}