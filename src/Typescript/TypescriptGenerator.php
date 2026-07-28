<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Typescript;

use Le0daniel\PhpTsBindings\Contracts\Branded;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BuiltInType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;
use Le0daniel\PhpTsBindings\Parser\Nodes\IntersectionNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BuiltInNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\DateTimeNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\EnumNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\ValueObjectNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Typescript\Data\EmissionContext;
use Le0daniel\PhpTsBindings\Typescript\Data\IO;
use Le0daniel\PhpTsBindings\Typescript\Data\Options;
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
    public function toTypescript(NodeInterface $node, IO $io, Options $options = new Options()): TypeScript
    {
        // The caller's registry is never touched: aliases collected during the walk land in a copy,
        // and that copy is what travels back out.
        $registry = clone $options->registry;
        $type = $this->emit($node, new EmissionContext($io, $options, $registry), 0);

        return new TypeScript($type, $registry);
    }

    /**
     * @param int $depth Nesting level of object literals, used for indentation when pretty printing.
     */
    private function emit(NodeInterface $node, EmissionContext $context, int $depth): string
    {
        return match (true) {
            $node instanceof BuiltInNode => $this->brand(self::builtIn($node->type), $node, $context),
            $node instanceof ValueObjectNode => $this->brand(self::builtIn($node->backingType), $node, $context),
            $node instanceof LiteralNode => self::literal($node),
            $node instanceof EnumNode => self::enum($node, $context),
            $node instanceof DateTimeNode => 'string',
            $node instanceof StructNode => $this->struct($node, $context, $depth),
            $node instanceof UnionNode => $this->union($node, $context, $depth),
            $node instanceof IntersectionNode => $this->intersection($node, $context, $depth),
            $node instanceof TupleNode => $this->tuple($node, $context, $depth),
            $node instanceof ListNode => "Array<{$this->emit($node->node, $context, $depth)}>",
            $node instanceof RecordNode => $context->options->pretty
                ? "Record<string, {$this->emit($node->node, $context, $depth)}>"
                : "Record<string,{$this->emit($node->node, $context, $depth)}>",
            $node instanceof ConstraintNode => $this->emit($node->node, $context, $depth),
            $node instanceof CustomCastingNode => $this->customCasting($node, $context, $depth),

            // NamedNode is the superseded branding path and is never constructed; ReferencedNode
            // only exists inside optimizer generated PHP, where it resolves against a registry the
            // generator does not have. Both are genuinely unrepresentable here.
            default => throw UnsupportedTypeException::forNode($node),
        };
    }

    private static function builtIn(BuiltInType $type): string
    {
        return match ($type) {
            BuiltInType::STRING => 'string',
            BuiltInType::INT, BuiltInType::FLOAT => 'number',
            BuiltInType::BOOL => 'boolean',
            BuiltInType::NULL => 'null',
            BuiltInType::MIXED => 'unknown',
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

    private static function enum(EnumNode $node, EmissionContext $context): string
    {
        $cases = array_map(
            fn(UnitEnum $case): string => Syntax::stringLiteral($case->name),
            $node->enumClassName::cases(),
        );

        if ($cases === []) {
            throw UnsupportedTypeException::emptyEnum($node->enumClassName);
        }

        return implode($context->options->pretty ? ' | ' : '|', $cases);
    }

    /**
     * A branded leaf is always referenced by its alias; the definition it stands for travels back
     * in TypeScript::$registry.
     */
    private function brand(string $baseType, Branded $node, EmissionContext $context): string
    {
        if ($context->options->ignoreBrandedTypes) {
            return $baseType;
        }

        $brandName = $node->brandName();
        if ($brandName === null || $brandName === '') {
            return $baseType;
        }

        $alias = Syntax::brandAlias($brandName);
        $context->registry->set($alias, Syntax::branded($baseType, $brandName));

        return $alias;
    }

    private function customCasting(CustomCastingNode $node, EmissionContext $context, int $depth): string
    {
        // The class exists on the wire in one direction only: it can be serialized, but there is
        // no way to build an instance from an incoming payload.
        if ($context->io === IO::INPUT && $node->strategy === ObjectCastStrategy::NEVER) {
            throw UnsupportedTypeException::uncastableInput($node);
        }

        return $this->emit($node->node, $context, $depth);
    }

    private function struct(StructNode $node, EmissionContext $context, int $depth): string
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
                Syntax::objectKey($property->name, $property->isOptional),
                $this->emit($property->node, $context, $depth + 1),
            ];
        }

        if ($properties === []) {
            return '{}';
        }

        if (!$context->options->pretty) {
            return '{' . implode('', array_map(
                    fn(array $property): string => "{$property[0]}:{$property[1]};",
                    $properties,
                )) . '}';
        }

        $indent = Syntax::indent($depth + 1);
        $lines = array_map(
            fn(array $property): string => "{$indent}{$property[0]}: {$property[1]};",
            $properties,
        );

        return "{\n" . implode("\n", $lines) . "\n" . Syntax::indent($depth) . '}';
    }

    /**
     * @param UnionNode<NodeInterface> $node
     */
    private function union(UnionNode $node, EmissionContext $context, int $depth): string
    {
        $members = array_map(
            function (NodeInterface $member) use ($context, $depth): string {
                $definition = $this->emit($member, $context, $depth);
                $declaring = self::declaringNode($member);

                return $declaring instanceof UnionNode || $declaring instanceof IntersectionNode
                    ? "({$definition})"
                    : $definition;
            },
            $node->types,
        );

        // Distinct schema nodes can render to the same type: `int|float` is one `number`.
        return implode($context->options->pretty ? ' | ' : '|', array_unique($members));
    }

    private function intersection(IntersectionNode $node, EmissionContext $context, int $depth): string
    {
        $members = array_map(
            function (NodeInterface $member) use ($context, $depth): string {
                $definition = $this->emit($member, $context, $depth);

                return self::declaringNode($member) instanceof UnionNode
                    ? "({$definition})"
                    : $definition;
            },
            $node->types,
        );

        return implode($context->options->pretty ? ' & ' : '&', $members);
    }

    private function tuple(TupleNode $node, EmissionContext $context, int $depth): string
    {
        $members = array_map(
            fn(NodeInterface $member): string => $this->emit($member, $context, $depth),
            $node->types,
        );

        return '[' . implode($context->options->pretty ? ', ' : ',', $members) . ']';
    }

    /**
     * Constraints are invisible in TypeScript, so precedence is decided by what they wrap.
     */
    private static function declaringNode(NodeInterface $node): NodeInterface
    {
        while ($node instanceof ConstraintNode) {
            $node = $node->node;
        }
        return $node;
    }
}
