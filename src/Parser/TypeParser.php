<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Contracts\Parser;
use Le0daniel\PhpTsBindings\Parser\Data\ParsingContext;
use Le0daniel\PhpTsBindings\Parser\Definition\ParsedTokens;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use Le0daniel\PhpTsBindings\Parser\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BuiltInType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BuiltInNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Parser\Parsers\CustomClassParser;
use Le0daniel\PhpTsBindings\Parser\Parsers\DateTimeParser;
use Le0daniel\PhpTsBindings\Parser\Parsers\EnumCasesParser;
use Le0daniel\PhpTsBindings\Validators\LengthValidator;
use ReflectionClass;
use Throwable;
use UnitEnum;

final readonly class TypeParser
{
    /**
     * @var list<Parser>
     */
    private array $parsers;

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
     * @param TypeStringTokenizer $tokenizer
     * @param list<Parser>|null $parsers
     */
    public function __construct(
        private TypeStringTokenizer $tokenizer = new TypeStringTokenizer(),
        array|null                  $parsers = null,
    )
    {
        $this->parsers = $parsers ?? self::getDefaultParsers();
    }

    /**
     * @param list<Parser> $prepend
     * @param list<Parser> $append
     * @return list<Parser>
     */
    public static function getDefaultParsers(array $prepend = [], array $append = []): array
    {
        return [
            ...$prepend,
            new EnumCasesParser(),
            new DateTimeParser(),
            new CustomClassParser(),
            ...$append,
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
        $tokens = new ParsedTokens(
            $typeString,
            $this->tokenizer->tokenize($typeString),
            $context,
        );

        return $this->consumeTypeOrUnion($tokens);
    }

    /**
     * @throws InvalidSyntaxException
     */
    private function consumeCustomType(ParsedTokens $tokens): NodeInterface
    {
        $token = $tokens->current();
        $fqcn = $tokens->context->toFullyQualifiedClassName($token->value);
        $tokens->advance();

        foreach ($this->parsers as $parser) {
            if ($parser->canParse($fqcn, $token)) {
                return $parser->parse(
                    $fqcn,
                    $token,
                    $this
                );
            }
        }

        $this->produceSyntaxError("Could not parse custom type: {$token->value}", null);
    }

    private function consumeTypeModifiers(ParsedTokens $tokens, NodeInterface $type): NodeInterface
    {
        while ($tokens->current()->is(TokenType::CLOSED_BRACKETS)) {
            $tokens->advance();
            $type = new ListNode($type);
        }
        return $type;
    }

    /**
     * @param ParsedTokens $tokens
     * @return NodeInterface
     * @throws InvalidSyntaxException
     */
    private function consumeType(ParsedTokens $tokens): NodeInterface
    {
        $token = $tokens->current();

        // Handles primitive literals in schema
        if ($token->isAnyTypeOf(TokenType::BOOL, TokenType::STRING, TokenType::FLOAT, TokenType::INT)) {
            $tokens->advance();
            return new LiteralNode(
                LiteralType::identifyPrimitiveTypeValue($token->value),
                $token->coercedValue(),
            );
        }

        // We handle class const literals
        if ($token->is(TokenType::CLASS_CONST)) {
            [$className, $constOrEnumCase] = explode('::', $token->value);
            $fqcn = $tokens->context->toFullyQualifiedClassName($className);

            try {
                $reflection = new ReflectionClass($fqcn);
                $const = $reflection->getConstant($constOrEnumCase);
                $isEnum = $const instanceof UnitEnum;
                $tokens->advance();

                return new LiteralNode(
                    $isEnum ? LiteralType::ENUM_CASE : LiteralType::identifyPrimitiveTypeValue($const),
                    $const
                );
            } catch (Throwable $exception) {
                $this->produceSyntaxError("Could not identify class const or enum", $tokens, $exception);
            }
        }

        if (!$token->is(TokenType::IDENTIFIER)) {
            $this->produceSyntaxError("Expected type identifier", $tokens);
        }

        // Recursive support for locally defined types using @phpstan-type.
        if ($tokens->context->isLocalType($token->value)) {
            $tokens->advance();
            return $this->parse(
                $tokens->context->getLocalTypeDefinition($token->value),
                $tokens->context,
            );
        }

        // Recursive support for imported types using @phpstan-import-type.
        if ($tokens->context->isImportedType($token->value)) {
            $tokens->advance();

            // ToDo: Cache this step as it could be expensive.
            $importDefinition = $tokens->context->getImportedTypeInfo($token->value);
            return $this->parse(
                $importDefinition['typeName'],
                ParsingContext::fromClassString($importDefinition['className']),
            );
        }

        // ToDo: Implement: non-empty-string|non-falsy-string|truthy-string
        // ToDo: Implement typeAliases support: https://phpstan.org/writing-php-code/phpdoc-types
        switch ($token->value) {
            case 'int':
                $tokens->advance();
                $generics = $this->consumeGenerics($tokens, max: 2);

                if (empty($generics)) {
                    return new BuiltInNode(BuiltInType::INT);
                }

                if (count($generics) !== 2) {
                    $this->produceSyntaxError("Expected 2 generics for int: int<min, max>", $tokens);
                }

                // ToDo: Properly handle generics validation here.
                $this->produceSyntaxError("Generics on int type not yet implemented.", $tokens);
            case 'string':
            case 'bool':
            case 'null':
            case 'float':
            case 'mixed':
                $tokens->advance();
                return new BuiltInNode(BuiltInType::from($token->value));
            case 'scalar':
                $tokens->advance();
                return new UnionNode([
                    new BuiltInNode(BuiltInType::INT),
                    new BuiltInNode(BuiltInType::FLOAT),
                    new BuiltInNode(BuiltInType::BOOL),
                    new BuiltInNode(BuiltInType::STRING),
                ]);
            case 'positive-int':
                $tokens->advance();
                return new ConstraintNode(
                    new BuiltInNode(BuiltInType::INT),
                    [new LengthValidator(min: 1, including: true)]
                );
            case 'negative-int':
                $tokens->advance();
                return new ConstraintNode(
                    new BuiltInNode(BuiltInType::INT),
                    [new LengthValidator(max: -1, including: true)]
                );
            case "non-negative-int":
                $tokens->advance();
                return new ConstraintNode(
                    new BuiltInNode(BuiltInType::INT),
                    [new LengthValidator(min: 0, including: true)]
                );
            case 'non-positive-int':
                $tokens->advance();
                return new ConstraintNode(
                    new BuiltInNode(BuiltInType::INT),
                    [new LengthValidator(max: 0, including: true)]
                );
            case 'numeric':
                $tokens->advance();
                return new UnionNode([
                    new BuiltInNode(BuiltInType::INT),
                    new BuiltInNode(BuiltInType::FLOAT),
                ]);
            case 'array':
            case 'non-empty-array':
            case 'list':
            case 'non-empty-list':
                return $this->consumeArrayType($tokens);
            case 'object':
                return $this->consumeStruct($tokens);
            default:
                return $this->consumeCustomType($tokens);
        }
    }

    /**
     * @throws InvalidSyntaxException
     */
    private function consumeStruct(ParsedTokens $tokens): NodeInterface
    {
        if (!$tokens->currentTokenIs(TokenType::IDENTIFIER) || !$tokens->currentValueIn('object', 'array')) {
            $this->produceSyntaxError("Expected object", $tokens);
        }

        $structType = StructPhpType::from($tokens->current()->value);

        $tokens->advance();
        if (!$tokens->current()->is(TokenType::LBRACE)) {
            return new RecordNode(new BuiltInNode(BuiltInType::MIXED));
        }

        $tokens->advance();
        $properties = [];

        // ToDo: Add Tuple support from PHPStan: array{int, int}
        while ($tokens->canAdvance()) {
            if (!$tokens->current()->is(TokenType::IDENTIFIER)) {
                $this->produceSyntaxError("Expected identifier", $tokens);
            }

            $name = $tokens->current()->value;
            $tokens->advance();
            $isOptional = $this->consumeOptionalObjectKey($tokens);

            if (!$tokens->current()->is(TokenType::COLON)) {
                $this->produceSyntaxError("Expected colon", $tokens);
            }
            $tokens->advance();

            $type = $this->consumeTypeOrUnion($tokens, TokenType::COMMA, TokenType::RBRACE);
            $properties[] = new PropertyNode($name, $type, $isOptional);
            if ($tokens->current()->is(TokenType::RBRACE)) {
                break;
            }
            $tokens->advance();
        }

        if (!$tokens->current()->is(TokenType::RBRACE)) {
            $this->produceSyntaxError("Expected brace", $tokens);
        }

        if (empty($properties)) {
            $this->produceSyntaxError("Expected properties", $tokens);
        }

        // We move out of the object
        $tokens->advance();
        return new StructNode($structType, $properties);
    }

    private function consumeOptionalObjectKey(ParsedTokens $tokens): bool
    {
        if ($tokens->current()->is(TokenType::QUESTION_MARK)) {
            $tokens->advance();
            return true;
        }
        return false;
    }

    /**
     * @throws InvalidSyntaxException
     */
    private function consumeTypeOrUnion(ParsedTokens $tokens, TokenType ...$stopAt): NodeInterface
    {
        $openUnion = true;
        $nullableByQuestionMark = false;
        $types = [];

        if ($tokens->currentTokenIs(TokenType::QUESTION_MARK)) {
            $nullableByQuestionMark = true;
            $tokens->advance();
            $types[] = new BuiltInNode(BuiltInType::NULL);
        }

        do {
            $token = $tokens->current();

            // If we reach an ending token, we stop without consuming it.
            if ($token->isAnyTypeOf(TokenType::EOF, ...$stopAt)) {
                break;
            }

            if ($token->is(TokenType::PIPE)) {
                if ($openUnion) {
                    $this->produceSyntaxError("Expected Type Identifier, got Pipe", $tokens);
                }

                // Case where we have ?int|string. This is unsupported in PHP. We though support it through ().
                // So (?int)|string is supported but equivalent to null|int|string.
                if ($nullableByQuestionMark) {
                    $this->produceSyntaxError("Cannot use ?type as nullable and pipe at the same time", $tokens);
                }

                $openUnion = true;
                $tokens->advance();
                continue;
            }

            if ($token->is(TokenType::LPAREN)) {
                $tokens->advance();
                $grouped = $this->consumeTypeOrUnion($tokens, TokenType::RPAREN);
                if (!$tokens->current()->is(TokenType::RPAREN)) {
                    $this->produceSyntaxError("Expected closing parenthesis", $tokens);
                }
                $tokens->advance();
                $types[] = $this->consumeTypeModifiers($tokens, $grouped);
                $openUnion = false;
                continue;
            }

            $types[] = $this->consumeTypeModifiers($tokens, $this->consumeType($tokens));
            $openUnion = false;
        } while ($tokens->canAdvance());

        if ($openUnion) {
            $this->produceSyntaxError("Expected type Identifier", $tokens);
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

    // ToDo: Move this to the ast optimizer. The local version is not optimized at all.
    /**
     *
     * @param non-empty-list<NodeInterface> $types
     * @return UnionNode
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
            if ($property->type instanceof LiteralNode) {
                $candidateFields[$property->name] = $property->type->value;
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
                    !$otherProperty?->type instanceof LiteralNode ||
                    in_array($otherProperty->type->value, $values, true)
                ) {
                    $isDiscriminator = false;
                    break; // This is not the discriminator field
                }
                $values[] = $otherProperty->type->value;
            }

            if ($isDiscriminator) {
                // We found it!
                return new UnionNode($types, $fieldName, $values);
            }
        }

        return new UnionNode($types);
    }

    /**
     * @throws InvalidSyntaxException
     */
    private function consumeArrayType(ParsedTokens $tokens): NodeInterface
    {
        // ToDo: Handle non-empty-array | non-empty-list;
        if (!$tokens->current()->is(TokenType::IDENTIFIER) || !in_array($tokens->current()->value, ['array', 'list'], true)) {
            $this->produceSyntaxError("Expected Array Type Identifier: array or list", $tokens);
        }

        // Handle array structures.
        if ($tokens->current()->value === 'array' && $tokens->nextTokenIs(TokenType::LBRACE)) {

            // Handles: array{0: string, 1: int} => tuple
            if ($tokens->peek(2)?->type === TokenType::INT && $tokens->peek(3)?->isAnyTypeOf(TokenType::COLON, TokenType::RBRACE)) {
                return $this->consumeIntegerDeterminedTuple($tokens);
            }

            // Handles: array{string,int} => tuple
            if ($tokens->peek(3)?->isAnyTypeOf(TokenType::COMMA, TokenType::RBRACE)) {
                return $this->consumeTuple($tokens);
            }

            return $this->consumeStruct($tokens);
        }

        $maxGenerics = $tokens->current()->value === 'list' ? 1 : 2;

        // Consuming of the array type identifier
        $tokens->advance();

        // No generics
        if (!$tokens->currentTokenIs(TokenType::LT)) {
            return new ListNode(new BuiltInNode(BuiltInType::MIXED));
        }

        $generics = $this->consumeGenerics($tokens, min: 1, max: $maxGenerics);

        if (count($generics) === 1) {
            return new ListNode($generics[0]);
        }

        $keyType = $generics[0];
        if (!$keyType instanceof BuiltInNode || $keyType->type !== BuiltInType::STRING) {
            $this->produceSyntaxError("Array key type must be 'string'. Got: {$keyType}", $tokens);
        }

        return new RecordNode($generics[1]);
    }

    /**
     * @throws InvalidSyntaxException
     */
    private function consumeTuple(ParsedTokens $tokens): TupleNode
    {
        if (!$tokens->currentTokenIs(TokenType::IDENTIFIER, 'array')) {
            $this->produceSyntaxError("Expected array", $tokens);
        }
        $tokens->advance();

        if (!$tokens->currentTokenIs(TokenType::LBRACE)) {
            $this->produceSyntaxError("Expected {", $tokens);
        }
        $tokens->advance();

        $types = [];
        while ($tokens->canAdvance()) {
            $types[] = $this->consumeTypeOrUnion($tokens, TokenType::COMMA, TokenType::RBRACE);

            if ($tokens->currentTokenIs(TokenType::RBRACE)) {
                break;
            }

            if (!$tokens->currentTokenIs(TokenType::COMMA)) {
                $this->produceSyntaxError("Expected comma for union: array{string, int}", $tokens);
            }
            $tokens->advance();
        }

        $tokens->advance();
        return new TupleNode($types);
    }

    /**
     * @throws InvalidSyntaxException
     */
    private function consumeIntegerDeterminedTuple(ParsedTokens $tokens): TupleNode
    {
        if (!$tokens->currentTokenIs(TokenType::IDENTIFIER, 'array')) {
            $this->produceSyntaxError("Expected array", $tokens);
        }
        $tokens->advance();

        if (!$tokens->currentTokenIs(TokenType::LBRACE)) {
            $this->produceSyntaxError("Expected {", $tokens);
        }
        $tokens->advance();

        $types = [];
        while ($tokens->canAdvance()) {
            if ($tokens->currentTokenIs(TokenType::RBRACE)) {
                break;
            }

            if ($tokens->currentTokenIs(TokenType::COMMA)) {
                $tokens->advance();
                continue;
            }

            if (!$tokens->currentTokenIs(TokenType::INT, (string)count($types))) {
                $this->produceSyntaxError("Expected int with value " . count($types), $tokens);
            }
            $tokens->advance();

            if (!$tokens->currentTokenIs(TokenType::COLON)) {
                $this->produceSyntaxError("Expected colon", $tokens);
            }
            $tokens->advance();
            $types[] = $this->consumeTypeOrUnion($tokens, TokenType::COMMA, TokenType::RBRACE);
        }

        $tokens->advance();
        return new TupleNode($types);
    }

    /**
     * @throws InvalidSyntaxException
     * @return list<NodeInterface>
     */
    private function consumeGenerics(ParsedTokens $tokens, ?int $min = null, ?int $max = null): array
    {
        $isGenericBlock = $tokens->currentTokenIs(TokenType::LT);
        $generics = [];

        // No Generics
        if (!$isGenericBlock) {
            if (isset($min)) {
                $this->produceSyntaxError("Expected at least {$min} generics, got 0.", $tokens);
            }
            return [];
        }

        while ($tokens->canAdvance()) {
            if ($tokens->currentTokenIs(TokenType::GT)) {
                break;
            }
            $tokens->advance();
            $generics[] = $this->consumeTypeOrUnion($tokens, TokenType::COMMA, TokenType::GT);
        }

        if (!$tokens->currentTokenIs(TokenType::GT)) {
            $this->produceSyntaxError("Expected '>' to end generics", $tokens);
        }

        if (empty($generics)) {
            $this->produceSyntaxError("Expected at least one generic type, got none", $tokens);
        }

        if (isset($min) && count($generics) < $min) {
            $this->produceSyntaxError("Expected at least {$min} generic type(s), got " . count($generics), $tokens);
        }

        if (isset($max) && count($generics) > $max) {
            $this->produceSyntaxError("Expected at most {$max} generic type(s), got " . count($generics), $tokens);
        }

        $tokens->advance();

        return $generics;
    }

    /**
     * @throws InvalidSyntaxException
     */
    private function produceSyntaxError(string $message, ?ParsedTokens $tokens = null, ?Throwable $exception = null): never
    {
        throw new InvalidSyntaxException(
            implode(PHP_EOL, array_filter([
                "Syntax Error: {$message}",
                $tokens?->highlightCurrentToken(),
            ])),
            previous: $exception,
        );
    }
}