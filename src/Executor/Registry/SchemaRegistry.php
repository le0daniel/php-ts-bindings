<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Registry;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;

final class SchemaRegistry
{
    /**
     * @var array<string, NodeInterface>
     */
    private array $instantiatedNodes = [];

    /**
     * @param array<string, Closure(SchemaRegistry): NodeInterface> $registeredSchemas
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