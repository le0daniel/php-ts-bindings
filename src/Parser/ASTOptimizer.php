<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\Constraint;
use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Exceptions\UnknownTypeKeyException;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\IntersectionNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\MetadataNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ReferencedNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Parser\Registry\CachedTypeRegistry;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use RuntimeException;

final class ASTOptimizer
{
    /**
     * Identifiers are content derived: sha1 of the node's exported PHP, truncated. A parent's id
     * therefore depends only on its children's content, never on traversal order, which keeps the
     * generated artifact byte identical across machines.
     *
     * @var array<string, array{NodeInterface, string}> id => [node, exported code]
     */
    private array $dedupedNodes = [];

    private const string KEY_VARIABLE_NAME = 'key';

    public function __construct(
        private readonly string $registryVariableName = 'r',
        private readonly int $idLength = 10,
    )
    {
        if ($this->registryVariableName === self::KEY_VARIABLE_NAME) {
            throw new RuntimeException(
                "The registry variable cannot be named '" . self::KEY_VARIABLE_NAME
                . "'; it would collide with the generated factory's key parameter.",
            );
        }
    }

    /**
     * Interns a node under a content derived id and returns the reference that replaces it.
     *
     * Identity is exportPhpCode(), not __toString(): the registry entry for an interned node IS
     * its exported code, so two nodes exporting the same PHP are interchangeable by definition.
     * __toString() is lossy — ConstraintNode and MetadataNode both delegate to their inner node —
     * and using it here silently merged schemas that differ in validation.
     */
    private function intern(string $prefix, NodeInterface $node, string $originalTypeString): ReferencedNode
    {
        $exported = $node->exportPhpCode();
        $identifier = '#' . $prefix . substr(sha1($exported), 0, $this->idLength);

        if (isset($this->dedupedNodes[$identifier]) && $this->dedupedNodes[$identifier][1] !== $exported) {
            throw new RuntimeException(
                "Identity hash collision on '{$identifier}'. Increase the idLength of the ASTOptimizer.",
            );
        }

        $this->dedupedNodes[$identifier] = [$node, $exported];

        return new ReferencedNode($identifier, $originalTypeString, $this->registryVariableName);
    }

    /**
     * @param array<string, NodeInterface> $nodes
     */
    public function optimizeAndWriteToFile(string $fileName, array $nodes): void
    {
        PHPExport::writeFileAtomically($fileName, <<<PHP
<?php declare(strict_types=1);
return {$this->generateOptimizedCode($nodes)};
PHP);
    }

    /**
     * @param array<string, NodeInterface|Closure(): NodeInterface> $nodes
     */
    public function generateOptimizedCode(array $nodes): string
    {
        if (array_any(array_keys($nodes), fn(string $key) => str_starts_with($key, '#'))) {
            throw new RuntimeException('The keys of the nodes MUST not start with a # character');
        }

        $this->dedupedNodes = [];

        $optimizedNodes = array_map(
            fn(Closure|NodeInterface $node) => $this->dedupeNode($node instanceof Closure ? $node() : $node),
            $nodes
        );

        $registryClass = PHPExport::absolute(CachedTypeRegistry::class);
        $nodeInterface = PHPExport::absolute(NodeInterface::class);
        $unknownKeyException = PHPExport::absolute(UnknownTypeKeyException::class);

        $internedArms = Arrays::mapWithKeys(
            $this->dedupedNodes,
            fn(string $key, array $entry) => PHPExport::export($key) . " => {$entry[1]},",
        );

        $schemaArms = Arrays::mapWithKeys(
            $optimizedNodes,
            fn(string $key, NodeInterface $ast) => PHPExport::export($key) . " => {$ast->exportPhpCode()},"
        );

        $arms = implode(PHP_EOL, [... $internedArms, ... $schemaArms]);
        $key = self::KEY_VARIABLE_NAME;

        // One match arm per entry rather than one closure per entry: arms are only evaluated when
        // their key is requested, so this stays lazy while allocating nothing per entry.
        return "new {$registryClass}(static function (string \${$key}, {$registryClass} \${$this->registryVariableName}): {$nodeInterface} { "
            . "return match (\${$key}) { {$arms} default => throw {$unknownKeyException}::forKey(\${$key}) }; })";
    }

    /**
     * @template T of NodeInterface
     * @param T $node
     * @return T|ReferencedNode
     */
    private function dedupeNode(NodeInterface $node): NodeInterface
    {
        // Codegen metadata (brands, named types) has no runtime effect and is eliminated from
        // cached ASTs entirely: TypeScript generation runs on freshly parsed schemas.
        if ($node instanceof MetadataNode) {
            return $this->dedupeNode($node->node);
        }

        if ($node instanceof ReferencedNode) {
            return $node;
        }

        if ($node instanceof LeafNode) {
            return $this->intern('l', $node, (string)$node);
        }

        // Children are deduped first, so the interned node exports its children as short
        // `$registry->get('#…')` references. Hashing is therefore O(1) per node, not O(subtree).
        if ($node instanceof PropertyNode) {
            return $this->intern('p', new PropertyNode(
                $node->name,
                $this->dedupeNode($node->node),
                $node->isOptional,
                $node->propertyType
            ), (string)$node);
        }

        // Deep optimization
        if ($node instanceof StructNode) {
            return $this->intern('s', new StructNode(
                $node->phpType,
                array_map($this->dedupeNode(...), $node->properties),
            ), (string)$node);
        }

        // Composite nodes are rebuilt inline rather than interned: a single use composite costs
        // more as a registry entry than it does written out at the use site.
        return match ($node::class) {
            ConstraintNode::class => $this->flattenConstraintNode($node),
            CustomCastingNode::class => new CustomCastingNode(
                $this->dedupeNode($node->node),
                $node->fullyQualifiedCastingClass,
                $node->strategy,
            ),
            ListNode::class => new ListNode(
                $this->dedupeNode($node->node),
            ),
            RecordNode::class => new RecordNode(
                $this->dedupeNode($node->node),
            ),
            TupleNode::class => new TupleNode(
                array_map($this->dedupeNode(...), $node->types),
            ),
            UnionNode::class => new UnionNode(
                array_map($this->dedupeNode(...), $node->types),
                $node->discriminator,
                $node->discriminatorMap,
            ),
            IntersectionNode::class => new IntersectionNode(
                array_map($this->dedupeNode(...), $node->types),
            ),
            default => throw new RuntimeException('Unknown node type: ' . $node::class),
        };
    }

    private function flattenConstraintNode(ConstraintNode $node): ConstraintNode
    {
        /** @var list<Constraint> $constraints */
        $constraints = [];
        while ($node instanceof ConstraintNode) {
            array_push($constraints, ...$node->constraints);
            $node = $node->node;
        }

        return new ConstraintNode(
            $this->dedupeNode($node),
            $constraints,
        );
    }
}