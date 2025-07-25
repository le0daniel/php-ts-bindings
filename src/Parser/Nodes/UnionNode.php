<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BuiltInType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BuiltInNode;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

/**
 * @template T of NodeInterface
 */
final class UnionNode implements NodeInterface
{
    private bool $acceptsNull;

    // Improves the performance of nullable Unions.
    public function acceptsNull(): bool
    {
        return $this->acceptsNull ??= array_any($this->types, fn(NodeInterface $type) => $type instanceof BuiltInNode && $type->type === BuiltInType::NULL);
    }

    /**
     * @param list<T> $types
     * @param string|null $discriminator
     * @param list<string|bool|int>|null $discriminatorMap
     */
    public function __construct(
        public readonly array   $types,
        public readonly ?string $discriminator = null,
        public readonly ?array  $discriminatorMap = null,
    )
    {

    }

    public function validate(): void
    {
        if (count($this->types) < 2) {
            throw new InvalidArgumentException('Cannot create union type with less than 2 types');
        }
    }

    public function __toString(): string
    {
        return implode('|', array_map(fn(NodeInterface $type) => (string)$type, $this->types));
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
            return $this->types[$index];
        }
        return null;
    }

    public function exportPhpCode(): string
    {
        $classname = PHPExport::absolute(self::class);
        $types = PHPExport::export($this->types);
        $discriminator = $this->discriminator ? PHPExport::export($this->discriminator) : 'null';
        $discriminatorMap = $this->discriminatorMap ? PHPExport::export($this->discriminatorMap) : 'null';
        return "new {$classname}({$types}, {$discriminator}, {$discriminatorMap})";
    }
}