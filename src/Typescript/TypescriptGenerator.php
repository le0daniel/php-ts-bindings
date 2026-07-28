<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Typescript;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BackingType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;
use Le0daniel\PhpTsBindings\Parser\Nodes\IntersectionNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BoolNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\DateTimeNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\EnumNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\FloatNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\IntNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\MixedNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\NullNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\StringNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\ValueObjectNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\MetadataNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Typescript\Data\EmissionContext;
use Le0daniel\PhpTsBindings\Typescript\Data\IO;
use Le0daniel\PhpTsBindings\Typescript\Data\TypeRegistry;
use Le0daniel\PhpTsBindings\Typescript\Data\TypeScript;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnsupportedTypeException;
use Le0daniel\PhpTsBindings\Typescript\Utils\Syntax;
use UnitEnum;

/**
 * Turns a schema into TypeScript.
 *
 * Every rule about how a node looks in TypeScript lives in this package. The nodes themselves are
 * only read: the generator infers the type from the data a node already carries, never from a
 * definition the node prints about itself. If a schema describes something with no honest
 * TypeScript representation, generation fails instead of degrading to a placeholder.
 */
final readonly class TypescriptGenerator
{
    public function toTypescript(NodeInterface $node, IO $io, ?TypeRegistry $sharedRegistry = null): TypeScript
    {
        if ($io === IO::BOTH) {
            throw new InvalidArgumentException('Emit for IO::INPUT or IO::OUTPUT; IO::BOTH is only a #[Named] scope.');
        }

        // Every pass emits into its own local registry, so the result always carries exactly the
        // aliases this schema produced. When a shared registry is given, all of them are
        // registered into it after the pass — that hand-over is where an alias meaning two
        // different things across several schemas is rejected.
        $localRegistry = new TypeRegistry();
        $context = new EmissionContext($io, $localRegistry);
        $type = $this->emit($node, $context);

        foreach ($localRegistry->toArray() as $alias => $definition) {
            $sharedRegistry?->set($alias, $definition);
        }

        return new TypeScript($type, $localRegistry);
    }

    private function emit(NodeInterface $node, EmissionContext $context): string
    {
        return match (true) {
            $node instanceof MetadataNode => $this->metadata($node, $context),
            $node instanceof StringNode => 'string',
            $node instanceof IntNode, $node instanceof FloatNode => 'number',
            $node instanceof BoolNode => 'boolean',
            $node instanceof NullNode => 'null',
            $node instanceof MixedNode => 'unknown',
            $node instanceof ValueObjectNode => match ($node->backingType) {
                BackingType::STRING => 'string',
                BackingType::INT => 'number',
            },
            $node instanceof LiteralNode => self::literal($node),
            $node instanceof EnumNode => self::enum($node),
            $node instanceof DateTimeNode => 'string',
            $node instanceof StructNode => $this->struct($node, $context),
            $node instanceof UnionNode => $this->union($node, $context),
            $node instanceof IntersectionNode => $this->intersection($node, $context),
            $node instanceof TupleNode => $this->tuple($node, $context),
            $node instanceof ListNode => "Array<{$this->emit($node->node, $context)}>",
            $node instanceof RecordNode => "Record<string,{$this->emit($node->node, $context)}>",
            $node instanceof ConstraintNode => $this->emit($node->node, $context),
            $node instanceof CustomCastingNode => $this->customCasting($node, $context),

            // ReferencedNode only exists inside optimizer generated PHP, where it resolves against
            // a registry the generator does not have. It is genuinely unrepresentable here.
            default => throw UnsupportedTypeException::forNode($node),
        };
    }

    private static function literal(LiteralNode $node): string
    {
        return match ($node->type) {
            LiteralType::BOOL => $node->value ? 'true' : 'false',

            // Enum cases travel by name, never by backing value, so that is what the client sees.
            LiteralType::ENUM_CASE => $node->value instanceof UnitEnum
                ? Syntax::stringLiteral($node->value->name)
                : throw UnsupportedTypeException::forNode($node),

            LiteralType::STRING, LiteralType::INT, LiteralType::FLOAT, LiteralType::NULL
            => json_encode($node->value, JSON_THROW_ON_ERROR),
        };
    }

    private static function enum(EnumNode $node): string
    {
        $cases = array_map(
            fn(UnitEnum $case): string => Syntax::stringLiteral($case->name),
            $node->enumClassName::cases(),
        );

        if ($cases === []) {
            throw UnsupportedTypeException::emptyEnum($node->enumClassName);
        }

        return implode('|', $cases) |> Syntax::wrapInParentheses(...);
    }

    /**
     * Codegen metadata never nests (MetadataNode::validate()), so emission is one fixed pipeline:
     * emit the inner type, apply the brand, apply the name.
     *
     * A brand intersects the inner type with Brand<"..."> and is always parenthesised
     * (`(string & Brand<"email">)`), so the result composes into any surrounding type unchanged.
     * A name applying to the direction registers the result as an alias; the use site references
     * the bare identifier. The registry accepts the identical re-registration a second use site
     * produces and rejects a contradicting one.
     */
    private function metadata(MetadataNode $node, EmissionContext $context): string
    {
        $inner = $this->emit($node->node, $context);

        if ($node->brand !== null) {
            $inner = Syntax::branded($inner, $node->brand) |> Syntax::wrapInParentheses(...);
        }

        if ($node->name?->appliesTo($context->io)) {
            $context->registry->set($node->name->name, $inner);
            return $node->name->name;
        }

        return $inner;
    }

    private function customCasting(CustomCastingNode $node, EmissionContext $context): string
    {
        // The class exists on the wire in one direction only: it can be serialized, but there is
        // no way to build an instance from an incoming payload.
        if ($context->io === IO::INPUT && $node->strategy === ObjectCastStrategy::NEVER) {
            throw UnsupportedTypeException::uncastableInput($node);
        }

        return $this->emit($node->node, $context);
    }

    private function struct(StructNode $node, EmissionContext $context): string
    {
        /** @var list<array{string, string}> $properties */
        $properties = [];

        foreach ($node->properties as $property) {
            if (!$property instanceof PropertyNode) {
                throw UnsupportedTypeException::forNode($property);
            }

            $isVisible = $context->io === IO::INPUT
                ? $property->propertyType->isInput()
                : $property->propertyType->isOutput();

            if (!$isVisible) {
                continue;
            }

            $properties[] = [
                Syntax::objectKey($property->name, optional: $property->isOptional),
                $this->emit($property->node, $context),
            ];
        }

        if ($properties === []) {
            return '{}';
        }

        return '{' . implode('', array_map(
                fn(array $property): string => "{$property[0]}:{$property[1]};",
                $properties,
            )) . '}';
    }

    /**
     * @param UnionNode<NodeInterface> $node
     */
    private function union(UnionNode $node, EmissionContext $context): string
    {
        $members = array_map(
            fn($member): string => $this->emit($member, $context),
            $node->types,
        );

        // Distinct schema nodes can render to the same type: `int|float` is one `number`.
        return implode('|', array_unique($members)) |> Syntax::wrapInParentheses(...);
    }

    private function intersection(IntersectionNode $node, EmissionContext $context): string
    {
        $members = array_map(
            fn($member): string => $this->emit($member, $context),
            $node->types,
        );

        return implode('&', $members) |> Syntax::wrapInParentheses(...);
    }

    private function tuple(TupleNode $node, EmissionContext $context): string
    {
        $members = array_map(
            fn(NodeInterface $member): string => $this->emit($member, $context),
            $node->types,
        );

        return '[' . implode(',', $members) . ']';
    }
}
