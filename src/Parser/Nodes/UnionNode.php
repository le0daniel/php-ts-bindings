<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\ValidatableNode;
use Le0daniel\PhpTsBindings\Parser\Contracts\WrapsNodes;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\NullNode;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

/**
 * @template T of NodeInterface
 */
final class UnionNode implements NodeInterface, ValidatableNode, WrapsNodes
{
    private bool $acceptsNull;

    // Improves the performance of nullable Unions.
    public function acceptsNull(): bool
    {
        return $this->acceptsNull ??= array_any($this->nodes, fn(NodeInterface $type) => $type instanceof NullNode);
    }

    /**
     * @param list<T> $nodes
     * @param string|null $discriminator
     * @param list<string|bool|int>|null $discriminatorMap
     */
    public function __construct(
        public readonly array   $nodes,
        public readonly ?string $discriminator = null,
        public readonly ?array  $discriminatorMap = null,
    )
    {

    }

    #[Override]
    public function validate(): void
    {
        if (count($this->nodes) < 2) {
            throw new ParserException('Cannot create union type with less than 2 types');
        }
    }

    #[Override]
    public function __toString(): string
    {
        $types = implode('|', array_map(fn(NodeInterface $type) => (string)$type, $this->nodes));

        return $this->discriminator === null
            ? $types
            : "{$types} by '{$this->discriminator}'";
    }

    public function isDiscriminated(): bool
    {
        return $this->discriminator !== null;
    }

    public function getDiscriminatedType(mixed $value): ?NodeInterface
    {
        if (!$this->discriminatorMap) {
            return null;
        }

        $index = array_find_key($this->discriminatorMap, static fn(mixed $typeValue) => $typeValue === $value);
        if ($index !== null) {
            return $this->nodes[$index];
        }
        return null;
    }

    #[Override]
    public function exportPhpCode(): string
    {
        $classname = PHPExport::absolute(self::class);
        $types = PHPExport::export($this->nodes);
        $discriminator = $this->discriminator ? PHPExport::export($this->discriminator) : 'null';
        $discriminatorMap = $this->discriminatorMap ? PHPExport::export($this->discriminatorMap) : 'null';
        return "new {$classname}({$types}, {$discriminator}, {$discriminatorMap})";
    }
}