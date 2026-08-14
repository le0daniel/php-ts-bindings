<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Data;

use Closure;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;

final readonly class GlobalTypeAliases
{
    /**
     * @param  array<string, NodeInterface|Closure(): NodeInterface>  $aliases
     */
    public function __construct(
        public array $aliases = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return count($this->aliases) === 0;
    }

    public function isGlobalAlias(string $value): bool
    {
        return array_key_exists($value, $this->aliases);
    }

    public function getGlobalAlias(string $value): NodeInterface
    {
        $nodeOrNodeFactory = $this->aliases[$value];

        return $nodeOrNodeFactory instanceof Closure ? $nodeOrNodeFactory() : $nodeOrNodeFactory;
    }
}
