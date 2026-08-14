<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Helpers\Registry;

use Closure;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeRegistry;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\UnknownTypeKeyException;
use Override;

/**
 * Lazily instantiates schemas from generated code, memoizing each one.
 *
 * The factory is a single closure wrapping a match over every key, rather than an array holding
 * one closure per key: a match arm costs nothing until it is reached, so only the schemas a
 * request actually touches are ever built, and nothing is allocated per entry at load time.
 */
final class CachedTypeRegistry implements TypeRegistry
{
    /**
     * @var array<string, NodeInterface>
     */
    private array $instantiatedNodes = [];

    /**
     * @var Closure(string, self): NodeInterface
     */
    private readonly Closure $factory;

    /**
     * @param  Closure(string, self): NodeInterface|array<string, mixed>  $factory  The array form is
     *                                                                              the format written before schema identity was fixed and is rejected: such a cache can
     *                                                                              silently merge schemas that differ only in their constraints.
     */
    public function __construct(
        Closure|array $factory,
    ) {
        if (! $factory instanceof Closure) {
            throw UnknownTypeKeyException::forLegacyCacheShape();
        }

        $this->factory = $factory;
    }

    #[Override]
    public function get(string $key): NodeInterface
    {
        // An unknown key throws before the assignment, so misses are never memoized.
        return $this->instantiatedNodes[$key] ??= ($this->factory)($key, $this);
    }
}
