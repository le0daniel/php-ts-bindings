<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use DateTimeImmutable;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\PropertyType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\DateTimeNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\IntNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\StringNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

final class UtilsConsumer implements TypeConsumer
{
    use InteractsWithGenerics;

    public function canConsume(ParserState $state): bool
    {
        return $state->currentTokenIs(TokenType::IDENTIFIER)
            && in_array($state->current()->value, ['Pick', 'Omit', 'BrandedString', 'BrandedInt', 'DateTimeString'], true);
    }

    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $type = $state->current()->value;
        $state->advance();

        if ($type === 'DateTimeString') {
            // The format is optional: passing no minimum lets consumeGenerics return an empty
            // array when there is no generic block at all.
            $generics = $this->consumeGenerics($state, $parser, null, 1);
            if ($generics === []) {
                return new DateTimeNode(DateTimeImmutable::class);
            }

            [$formatNode] = $generics;
            return new DateTimeNode(
                DateTimeImmutable::class,
                $this->literalStringValue($state, $formatNode, 'date format'),
            );
        }

        if ($type === 'BrandedString' || $type === 'BrandedInt') {
            [$literalNode] = $this->consumeGenerics($state, $parser, 1, 1);
            $brand = $this->literalStringValue($state, $literalNode, 'branded type');

            return $type === 'BrandedString'
                ? new StringNode(brand: $brand)
                : new IntNode(brand: $brand);
        }

        [$nodeToPickFrom, $pick] = $this->consumeGenerics($state, $parser, 2, 2);

        if (!$nodeToPickFrom instanceof StructNode && !$nodeToPickFrom instanceof CustomCastingNode) {
            $state->produceSyntaxError("Expected struct or custom casting node for picking or omitting");
        }

        $structNode = $nodeToPickFrom instanceof CustomCastingNode
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
     * @param string $usage Named in the error message so it points at the utility type that failed.
     * @throws InvalidSyntaxException
     */
    private function literalStringValue(ParserState $state, NodeInterface $node, string $usage): string
    {
        if (!$node instanceof LiteralNode || $node->type !== LiteralType::STRING) {
            $state->produceSyntaxError("Expected literal string value for {$usage}, got: " . $node::class);
        }

        if (!is_string($node->value)) {
            $state->produceSyntaxError("Expected literal string value for {$usage}, got: " . gettype($node->value));
        }

        return $node->value;
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