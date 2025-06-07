<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Registry;

use Closure;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;

final class SchemaRegistry
{
    /**
     * @var array<string, StructNode>
     */
    private array $structs = [];

    /**
     * @param array<string, Closure(): StructNode> $registeredSchemas
     */
    public function __construct(
        private readonly array $registeredSchemas,
    )
    {
    }

    public function get(string $key): StructNode
    {
        return $this->structs[$key] ??= ($this->registeredSchemas[$key])();
    }
}