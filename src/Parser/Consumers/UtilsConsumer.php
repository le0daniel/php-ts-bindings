<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use DateTimeImmutable;
use DateTimeInterface;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Nodes\BrandedNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ObjectCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BuiltInType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\PropertyType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BuiltInNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\DateTimeNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

final class UtilsConsumer implements TypeConsumer
{
    use InteractsWithGenerics;

    public function canConsume(ParserState $state): bool
    {
        return in_array($state->current()->value, ['Pick', 'Omit', 'BrandedString', 'BrandedInt', 'DateTimeString'], true);
    }

    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $type = $state->current()->value;
        $state->advance();

        if ($type === 'DateTimeString') {
            $generics = $this->consumeGenerics($state, $parser, 0, 1);
            if (count($generics) === 1) {
                $formatString = $generics[0];
                if (!$formatString instanceof LiteralNode || $formatString->type !== LiteralType::STRING) {
                    $state->produceSyntaxError("Expected literal string value for DateTimeString format, got: " . $formatString::class);
                }

                return new DateTimeNode(
                    DateTimeImmutable::class,
                    (string) $formatString->value,
                );
            }

            return new DateTimeNode(
                DateTimeImmutable::class,
                DateTimeInterface::ATOM,
            );
        }

        if ($type === 'BrandedString' || $type === 'BrandedInt') {
            [$literalNode] = $this->consumeGenerics($state, $parser, 1, 1);
            if (!$literalNode instanceof LiteralNode || $literalNode->type !== LiteralType::STRING) {
                $state->produceSyntaxError("Expected literal string value for branded type, got: " . $literalNode::class);
            }

            $literalValue = $literalNode->value;
            if (!is_string($literalValue)) {
                $state->produceSyntaxError("Expected literal string value for branded type, got: " . gettype($literalValue));
            }

            return new BrandedNode(
                $literalValue,
                new BuiltInNode(
                    match ($type) {
                        'BrandedString' => BuiltInType::STRING,
                        'BrandedInt' => BuiltInType::INT,
                    },
                )
            );
        }

        [$nodeToPickFrom, $pick] = $this->consumeGenerics($state, $parser, 2, 2);

        if (!$nodeToPickFrom instanceof StructNode && !$nodeToPickFrom instanceof ObjectCastingNode) {
            $state->produceSyntaxError("Expected struct or custom casting node for picking or omitting");
        }

        $structNode = $nodeToPickFrom instanceof ObjectCastingNode
            // For a custom casting node, we pick from the object and create a new struct from it.
            ? $nodeToPickFrom->node
                ->filter(fn(PropertyNode $propertyNode): bool => $propertyNode->propertyType->isOutput())
                ->map(fn(PropertyNode $propertyType) => $propertyType->changePropertyType(PropertyType::BOTH))
                ->ofType(StructPhpType::OBJECT)
            : $nodeToPickFrom;

        return $structNode->filter(
            fn(PropertyNode $property): bool => match ($type) {
                'Pick' => in_array($property->name, $this->propertiesToPickOrOmit($state, $pick), true),
                'Omit' => !in_array($property->name, $this->propertiesToPickOrOmit($state, $pick), true),
                default => $state->produceSyntaxError("Expected Pick or Omit"),
            }
        );
    }


    /**
     * @param ParserState $state
     * @param NodeInterface $node
     * @return list<string>
     * @throws InvalidSyntaxException
     */
    private function propertiesToPickOrOmit(ParserState $state, NodeInterface $node): array
    {
        if ($node instanceof LiteralNode && $node->type === LiteralType::STRING) {
            return [(string)$node->value];
        }

        if (!$node instanceof UnionNode) {
            $state->produceSyntaxError("Expected union node or string literal for picking or omitting");
        }

        return array_map(function (NodeInterface $node) use ($state): string {
            if ($node instanceof LiteralNode && $node->type === LiteralType::STRING) {
                return (string)$node->value;
            }

            $type = $node::class;
            $state->produceSyntaxError("Expected string literal for picking or omitting, got: {$type}");
        }, $node->types);
    }
}