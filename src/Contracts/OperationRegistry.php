<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

use Le0daniel\PhpTsBindings\Server\Data\Operation;

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
     * @return array<string, Operation>
     */
    public function all(): array;
}