<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Registry;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;

/**
 * @template T of NodeInterface
 */
final class SchemaRegistry
{
    /**
     * @var array<string, T>
     */
    private array $instantiatedNodes = [];

    /**
     * @param array<string, Closure(SchemaRegistry): T> $registeredSchemas
     */
    public function __construct(
        private readonly array $registeredSchemas,
    )
    {
    }

    public function get(string $key): NodeInterface
    {
        return $this->instantiatedNodes[$key] ??= ($this->registeredSchemas[$key])($this);
    }
}