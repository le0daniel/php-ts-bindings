<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Operations;

use Le0daniel\PhpTsBindings\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Operations\Data\Endpoint;
use Le0daniel\PhpTsBindings\Operations\Data\OperationDefinition;

final class CachedOperationRegistry implements OperationRegistry
{
    /**
     * @param array<string, OperationDefinition> $operations
     */
    private array $instances = [];

    /**
     * @param array<string, \Closure(): OperationDefinition> $operations
     */
    public function __construct(private readonly array $operations)
    {
    }

    public function has(string $type, string $fullyQualifiedKey): bool
    {
        return array_key_exists("{$type}:{$fullyQualifiedKey}", $this->operations);
    }

    public function get(string $type, string $fullyQualifiedKey): Endpoint
    {
        $key = "{$type}:{$fullyQualifiedKey}";
        return $this->instances[$key] ??= $this->operations[$key]();
    }

    public function all(): array
    {
        foreach ($this->operations as $key => $operation) {
            $this->instances[$key] ??= $operation();
        }
        return $this->instances;
    }
}