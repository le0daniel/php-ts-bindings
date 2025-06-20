<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Registry;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Executor\Contracts\TypeRegistry;


final class CachedTypeRegistry implements TypeRegistry
{
    /**
     * @var array<string, NodeInterface>
     */
    private array $instantiatedNodes = [];

    /**
     * @param array<string, Closure(CachedTypeRegistry): NodeInterface> $registeredSchemas
     */
    public function __construct(
        private readonly array $registeredSchemas,
    )
    {
    }

    public function get(string $fullyQualifiedClassName): NodeInterface
    {
        return $this->instantiatedNodes[$fullyQualifiedClassName] ??= ($this->registeredSchemas[$fullyQualifiedClassName])($this);
    }
}