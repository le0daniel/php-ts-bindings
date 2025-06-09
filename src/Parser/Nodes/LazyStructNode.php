<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Exception;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;

/**
 * @internal
 */
final readonly class LazyStructNode implements NodeInterface
{
    public function __construct(
        public string $structName,
    )
    {
    }

    public function exportPhpCode(): string
    {
        return "\$registry->get('{$this->structName}')";
    }

    /**
     * @throws Exception
     */
    public function __toString(): never
    {
        throw new Exception('Not supported');
    }
}