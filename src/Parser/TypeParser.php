<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Consumers\AliasConsumer;
use Le0daniel\PhpTsBindings\Parser\Consumers\ArrayConsumer;
use Le0daniel\PhpTsBindings\Parser\Consumers\BuiltInLeafConsumer;
use Le0daniel\PhpTsBindings\Parser\Consumers\ClassConstConsumer;
use Le0daniel\PhpTsBindings\Parser\Consumers\DateTimeConsumer;
use Le0daniel\PhpTsBindings\Parser\Consumers\EnumConsumer;
use Le0daniel\PhpTsBindings\Parser\Consumers\IntConsumer;
use Le0daniel\PhpTsBindings\Parser\Consumers\LiteralConsumer;
use Le0daniel\PhpTsBindings\Parser\Consumers\StructConsumer;
use Le0daniel\PhpTsBindings\Parser\Consumers\UserDefinedObjectConsumer;
use Le0daniel\PhpTsBindings\Parser\Consumers\UtilsConsumer;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Data\GlobalTypeAliases;
use Le0daniel\PhpTsBindings\Parser\Data\ParsingContext;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Lexer\Exceptions\UnexpectedCharacterException;
use Le0daniel\PhpTsBindings\Parser\Lexer\Lexer;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;
use Le0daniel\PhpTsBindings\Parser\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BuiltInType;
use Le0daniel\PhpTsBindings\Parser\Nodes\IntersectionNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BuiltInNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;

final readonly class TypeParser
{
    /**
     * @var list<TypeConsumer>
     */
    private array $consumers;

    /**
     * Parsers take a type token and return a Node for this type. The Definitions live outside of the classes
     * that you wish to parse and serialize. They are only definitions, no less, no more.
     *
     * The Executor uses the definitions to execute your schema and create the correct classes for you at runtime,
     * verifying data integrity and type safety between client and server, bridging the gap between the two.
     *
     * It's best to run the parser in your build step to create a static file including all the definitions you need
     * at runtime.
     *
     * @param TypeConsumer[]|null $consumers
     */
    public function __construct(
        ?array $consumers = null,
    )
    {
        $this->consumers = $consumers ?? self::defaultConsumers();
    }

    /**
     * @param GlobalTypeAliases $globalTypeAliases
     * @param bool $allowAllObjectCasting
     * @return TypeConsumer[]
     */
    public static function defaultConsumers(
        GlobalTypeAliases $globalTypeAliases = new GlobalTypeAliases(),
        bool $allowAllObjectCasting = false,
    ): array
    {
        return [
            new LiteralConsumer(),
            new ClassConstConsumer(),
            new AliasConsumer($globalTypeAliases),
            new IntConsumer(),
            new BuiltInLeafConsumer(),
            new StructConsumer(),
            new ArrayConsumer(),
            new EnumConsumer(),
            new DateTimeConsumer(),
            new UserDefinedObjectConsumer($allowAllObjectCasting),
            new UtilsConsumer(),
        ];
    }

    /**
     * Parsing context is used to correctly Identify types that have been defined on the class level with
     * `phpstan-type` or `phpstan-import-type` on the class or file level.
     *
     * @throws InvalidSyntaxException
     */
    public function parse(string $typeString, ParsingContext $context = new ParsingContext()): NodeInterface
    {
        try {
            $tokens = new Lexer()->tokenize($typeString);
        } catch (UnexpectedCharacterException $exception) {
            // A character that cannot start a token is still a syntax error to everyone
            // outside the parser. InvalidSyntaxException is final and cannot be extended,
            // so the lexical failure is wrapped rather than allowed to escape.
            throw new InvalidSyntaxException(
                "Syntax Error: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        return $this->consume(new ParserState($typeString, $tokens, $context));
    }

    /**
     * The lexer no longer merges `[` and `]`, so both are matched here. The pair is required:
     * a lone `[` is left for the caller to fail on.
     */
    private function consumeTypeModifiers(ParserState $state, NodeInterface $type): NodeInterface
    {
        while ($state->current()->is(TokenType::LBRACKET) && $state->nextTokenIs(TokenType::RBRACKET)) {
            $state->advance(2);
            $type = new ListNode($type);
        }
        return $type;
    }

    /**
     * @param ParserState $state
     * @return NodeInterface
     * @throws InvalidSyntaxException
     */
    private function consumeType(ParserState $state): NodeInterface
    {
        // Delegate consumption of the actual type to the consumers.
        foreach ($this->consumers as $consumer) {
            if ($consumer->canConsume($state)) {
                return $consumer->consume($state, $this);
            }
        }

        $state->produceSyntaxError("No parser found.");
    }

    /**
     * @throws InvalidSyntaxException
     * @internal
     */
    public function consume(ParserState $state, TokenType ...$stopAt): NodeInterface
    {
        $types = [];
        $expectsType = true;

        /** @var null|'questionmark-union'|'union'|'intersection' $mode */
        $mode = null;

        if ($state->currentTokenIs(TokenType::QUESTION_MARK)) {
            $state->advance();
            $types[] = new BuiltInNode(BuiltInType::NULL);
            $mode = 'questionmark-union';
        }

        do {
            $token = $state->current();

            // If we reach an ending token, we stop without consuming it.
            if ($token->isAnyTypeOf(TokenType::EOF, ...$stopAt)) {
                break;
            }

            if ($token->is(TokenType::PIPE)) {
                $mode ??= 'union';
                if ($expectsType) {
                    $state->produceSyntaxError("Expected Type Identifier, got Pipe");
                }

                if ($mode !== 'union') {
                    $state->produceSyntaxError("Cannot mix union with intersection or nullable types. Use brackets to do so. Example: (A&B)|C or null|A|B");
                }

                $expectsType = true;
                $state->advance();
                continue;
            }

            if ($token->is(TokenType::AMPERSAND)) {
                $mode ??= 'intersection';
                if ($expectsType) {
                    $state->produceSyntaxError("Expected Type Identifier, got &");
                }

                if ($mode !== 'intersection') {
                    $state->produceSyntaxError("Cannot mix union and intersection types. Use brackets to do so. Example: (A&B)|C");
                }

                $expectsType = true;
                $state->advance();
                continue;
            }

            // Consume Groups
            if ($token->is(TokenType::LPAREN)) {
                $state->advance();
                $grouped = $this->consume($state, TokenType::RPAREN);
                if (!$state->current()->is(TokenType::RPAREN)) {
                    $state->produceSyntaxError("Expected closing parenthesis");
                }
                $state->advance();
                $types[] = $this->consumeTypeModifiers($state, $grouped);
                $expectsType = false;
                continue;
            }

            $types[] = $this->consumeTypeModifiers($state, $this->consumeType($state));
            $expectsType = false;
        } while ($state->canAdvance());

        if ($expectsType) {
            $state->produceSyntaxError("Expected type Identifier");
        }

        if ($mode === 'intersection') {
            if (count($types) < 2) {
                $state->produceSyntaxError("Intersections need at least 2 types.");
            }

            return new IntersectionNode($types);
        }

        if ($mode === 'questionmark-union') {
            if (count($types) !== 2) {
                $state->produceSyntaxError("Questionmark nullable unions need exactly 2 types. Example: ?MyClass, got: " . count($types) . " types.");
            }

            return new UnionNode($types, null, null);
        }

        return count($types) > 1
            ? $this->checkForDiscriminatedUnion(
                $this->flattenNestedUnionTypes($types)
            )
            : $types[0];
    }

    /**
     * @param list<NodeInterface> $types
     * @return list<NodeInterface>
     */
    private function flattenNestedUnionTypes(array $types): array
    {
        $flattened = [];

        foreach ($types as $type) {
            if ($type instanceof UnionNode) {
                array_push($flattened, ... $type->types);
                continue;
            }
            $flattened[] = $type;
        }

        return $flattened;
    }


    /**
     * @param non-empty-list<NodeInterface> $types
     * @return UnionNode<NodeInterface>
     */
    private function checkForDiscriminatedUnion(array $types): UnionNode
    {
        if (count($types) < 2 || !array_all($types, fn(NodeInterface $type) => $type instanceof StructNode)) {
            return new UnionNode($types);
        }

        /** @var StructNode $firstType */
        $firstType = $types[0];
        $candidateFields = [];

        // Step 1: Find candidate fields from the first type
        foreach ($firstType->properties as $property) {
            if ($property->node instanceof LiteralNode) {
                $candidateFields[$property->name] = $property->node->value;
            }
        }

        // Step 2: Iterate through candidates and verify with other types
        foreach ($candidateFields as $fieldName => $value) {
            $isDiscriminator = true;
            $values = [$value];

            // Start from the second type
            for ($i = 1; $i < count($types); $i++) {
                /** @var StructNode $otherType */
                $otherType = $types[$i];
                $otherProperty = $otherType->getProperty($fieldName);

                // Check for presence, type, and uniqueness
                if (
                    !$otherProperty?->node instanceof LiteralNode ||
                    in_array($otherProperty->node->value, $values, true)
                ) {
                    $isDiscriminator = false;
                    break; // This is not the discriminator field
                }
                $values[] = $otherProperty->node->value;
            }

            if ($isDiscriminator) {
                // We found it!
                return new UnionNode($types, $fieldName, $values);
            }
        }

        return new UnionNode($types);
    }
}