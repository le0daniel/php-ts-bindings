<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Operations\Contracts;

use Le0daniel\PhpTsBindings\Operations\Data\Endpoint;

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
     * @return Endpoint
     */
    public function get(string $type, string $fullyQualifiedKey): Endpoint;

    /**
     * @return list<Endpoint>
     */
    public function all(): array;
}