<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Operations;

use Closure;
use Le0daniel\PhpTsBindings\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Operations\Data\Operation;
use Le0daniel\PhpTsBindings\Operations\Data\OperationDefinition;

final class CachedOperationRegistry implements OperationRegistry
{
    /**
     * @var array<string, Operation>
     */
    private array $instances = [];

    /**
     * @param array<string, Closure(): Operation> $operations
     */
    public function __construct(private readonly array $operations)
    {
    }

    public function has(string $type, string $fullyQualifiedKey): bool
    {
        return array_key_exists("{$type}:{$fullyQualifiedKey}", $this->operations);
    }

    public function get(string $type, string $fullyQualifiedKey): Operation
    {
        $key = "{$type}:{$fullyQualifiedKey}";
        return $this->instances[$key] ??= $this->operations[$key]();
    }

    public function all(): array
    {
        foreach ($this->operations as $key => $factory) {
            $this->instances[$key] ??= $factory();
        }
        return array_values($this->instances);
    }
}