<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final readonly class UnionNode implements NodeInterface
{
    /**
     * @param array $types
     * @param string|null $discriminator
     * @param list<string|bool|int>|null $discriminatorMap
     */
    public function __construct(
        public array   $types,
        public ?string $discriminator = null,
        public ?array  $discriminatorMap = null,
    )
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