<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Helpers\Consumers;

use DateTimeImmutable;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Helpers\ParserState;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\NamedType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\PropertyType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\DateTimeNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\IntNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\StringNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\MetadataNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\InvalidStringLiteralException;
use Le0daniel\PhpTsBindings\Typescript\Utils\Syntax;
use Le0daniel\PhpTsBindings\Utils\Nodes;
use Override;

final readonly class UtilsConsumer implements TypeConsumer
{
    use InteractsWithGenerics;

    /**
     * Reserved words in docblocks: the consumer runs before UserDefinedObjectConsumer, so these
     * names win over a same-named imported class. See TypeParser::defaultConsumers().
     */
    private const array UTILITY_NAMES = ['Pick', 'Omit', 'BrandedString', 'BrandedInt', 'DateTimeString', 'Named', 'Branded'];

    #[Override]
    public function canConsume(ParserState $state): bool
    {
        return $state->currentTokenIs(TokenType::IDENTIFIER)
            && in_array($state->current()->value, self::UTILITY_NAMES, true);
    }

    #[Override]
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

            // Docblocks cannot carry #[Named], so the utility is the shorthand for brand + name:
            // the use site references the alias (Token), which resolves to `(string & Brand<"token">)`.
            if (! Syntax::isValidIdentifier($brand)) {
                throw InvalidStringLiteralException::notAValidTypescriptIdentifier($brand, "{$type}<'{$brand}'>");
            }

            return new MetadataNode(
                $type === 'BrandedString' ? new StringNode() : new IntNode(),
                NamedType::same(ucfirst($brand)),
                $brand,
            );
        }

        if ($type === 'Named') {
            return $this->named($state, $parser);
        }

        if ($type === 'Branded') {
            return $this->branded($state, $parser);
        }

        [$nodeToPickFrom, $pick] = $this->consumeGenerics($state, $parser, 2, 2);

        // Codegen metadata is irrelevant here: picking from a named or branded type produces a new
        // shape, so the alias and brand are dropped along the way.
        $nodeToPickFrom = Nodes::getDeclaringNode($nodeToPickFrom);

        if (! $nodeToPickFrom instanceof StructNode && ! $nodeToPickFrom instanceof CustomCastingNode) {
            $state->produceSyntaxError('Expected struct or custom casting node for picking or omitting');
        }

        if ($nodeToPickFrom instanceof CustomCastingNode) {
            // Only a struct has properties to pick from; a custom cast over a list or a record has
            // no named shape to narrow.
            $castFrom = $nodeToPickFrom->node;
            if (! $castFrom instanceof StructNode) {
                $state->produceSyntaxError('Cannot pick or omit from a custom casting node that does not wrap a struct');
            }

            // Picking from a castable object produces a new shape, so it is rebuilt as a plain
            // object struct with both directions enabled.
            $structNode = $castFrom
                ->filter(fn (PropertyNode $propertyNode): bool => $propertyNode->propertyType->isOutput())
                ->map(fn (PropertyNode $propertyType) => $propertyType->changePropertyType(PropertyType::BOTH))
                ->ofType(StructPhpType::OBJECT);
        } else {
            $structNode = $nodeToPickFrom;
        }

        return $structNode->filter(
            fn (PropertyNode $property): bool => match ($type) {
                'Pick' => in_array($property->name, $this->propertiesToPickOrOmit($state, $pick), true),
                'Omit' => ! in_array($property->name, $this->propertiesToPickOrOmit($state, $pick), true),
                default => $state->produceSyntaxError('Expected Pick or Omit'),
            }
        );
    }

    /**
     * @param  string  $usage  Named in the error message so it points at the utility type that failed.
     *
     * @throws InvalidSyntaxException
     */
    private function literalStringValue(ParserState $state, NodeInterface $node, string $usage): string
    {
        if (! $node instanceof LiteralNode || $node->type !== LiteralType::STRING) {
            $state->produceSyntaxError("Expected literal string value for {$usage}, got: ".$node::class);
        }

        if (! is_string($node->value)) {
            $state->produceSyntaxError("Expected literal string value for {$usage}, got: ".gettype($node->value));
        }

        return $node->value;
    }

    /**
     * `Named<'Name', T>` is the docblock form of `#[Named('Name')]`: attributes cannot reach into
     * a docblock, so the alias is attached at the use site instead.
     *
     * @throws InvalidSyntaxException
     */
    private function named(ParserState $state, TypeParser $parser): MetadataNode
    {
        [$literalNode, $innerNode] = $this->consumeGenerics($state, $parser, 2, 2);
        $name = $this->literalStringValue($state, $literalNode, 'named type');

        if (! Syntax::isValidIdentifier($name)) {
            throw InvalidStringLiteralException::notAValidTypescriptIdentifier($name, "Named<'{$name}', ...>");
        }

        [$node, $existingName, $existingBrand] = $this->unwrapMetadata($innerNode);

        if ($existingName !== null) {
            $state->produceSyntaxError(
                "The inner type of Named<'{$name}', ...> already carries the alias '{$existingName->outputName}'."
            );
        }

        return new MetadataNode($node, NamedType::same($name), $existingBrand);
    }

    /**
     * `Branded<'brand', T>` is `BrandedString<'brand'>` generalized to any inner type: brand plus
     * implicit alias in one. An inner `Named<...>` supplies the alias instead of the implicit one,
     * so `Branded<'accountId', Named<'AccountId', string>>` reads exactly as written.
     *
     * @throws InvalidSyntaxException
     */
    private function branded(ParserState $state, TypeParser $parser): MetadataNode
    {
        [$literalNode, $innerNode] = $this->consumeGenerics($state, $parser, 2, 2);
        $brand = $this->literalStringValue($state, $literalNode, 'branded type');

        if (! Syntax::isValidIdentifier($brand)) {
            throw InvalidStringLiteralException::notAValidTypescriptIdentifier($brand, "Branded<'{$brand}', ...>");
        }

        [$node, $existingName, $existingBrand] = $this->unwrapMetadata($innerNode);

        if ($existingBrand !== null) {
            $state->produceSyntaxError(
                "The inner type of Branded<'{$brand}', ...> already carries the brand '{$existingBrand}'."
            );
        }

        // ucfirst cannot invalidate the implicit alias: the identifier's first character class
        // ([A-Za-z_$]) is closed under it.
        return new MetadataNode($node, $existingName ?? NamedType::same(ucfirst($brand)), $brand);
    }

    /**
     * Flattens an inner MetadataNode so the utilities always produce a single wrapper: metadata
     * stays one flat node per position (MetadataNode::validate() rejects nesting), and each slot
     * is written exactly once — the callers reject a second name or brand.
     *
     * @return array{NodeInterface, NamedType|null, string|null}
     */
    private function unwrapMetadata(NodeInterface $node): array
    {
        return $node instanceof MetadataNode
            ? [$node->node, $node->name, $node->brand]
            : [$node, null, null];
    }

    /**
     * @return list<string>
     *
     * @throws InvalidSyntaxException
     */
    private function propertiesToPickOrOmit(ParserState $state, NodeInterface $node): array
    {
        if ($node instanceof LiteralNode && $node->type === LiteralType::STRING) {
            return [$node->stringValue()];
        }

        if (! $node instanceof UnionNode) {
            $state->produceSyntaxError('Expected union node or string literal for picking or omitting');
        }

        return array_map(function (NodeInterface $node) use ($state): string {
            if ($node instanceof LiteralNode && $node->type === LiteralType::STRING) {
                return $node->stringValue();
            }

            $type = $node::class;
            $state->produceSyntaxError("Expected string literal for picking or omitting, got: {$type}");
        }, $node->nodes);
    }
}
