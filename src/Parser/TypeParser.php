<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Contracts\Parser;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BuiltInType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListType;
use Le0daniel\PhpTsBindings\Parser\Nodes\OptionalType;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordType;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructType;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleType;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionType;
use Le0daniel\PhpTsBindings\Parser\Parsers\CustomClassParser;
use Le0daniel\PhpTsBindings\Parser\Parsers\DateTimeParser;
use Le0daniel\PhpTsBindings\Parser\Parsers\EnumCasesParser;
use Le0daniel\PhpTsBindings\Utils\Strings;
use RuntimeException;

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
        private TypeStringTokenizer $tokenizer,
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
     * @param string $typeString
     * @param array<int|string, class-string> $namespaces
     * @return NodeInterface
     */
    public function parse(string $typeString, array $namespaces = []): NodeInterface
    {
        $tokens = $this->tokenizer->tokenize($typeString, $namespaces);
        return $this->consumeTypeOrUnion($tokens);
    }

    private function parseCustomType(Token $token): NodeInterface
    {
        foreach ($this->parsers as $parser) {
            if ($parser->canParse($token)) {
                return $parser->parse($token, $this);
            }
        }

        $this->produceSyntaxError("Could not parse custom type: {$token->value}", null);
    }

    private function consumeTypeModifiers(Tokens $tokens, NodeInterface $type): NodeInterface
    {
        while ($tokens->current()?->is(TokenType::CLOSED_BRACKETS)) {
            $tokens->advance();
            $type = new ListType($type);
        }
        return $type;
    }

    private function consumeType(Tokens $tokens): NodeInterface
    {
        $token = $tokens->current();

        // Handles literals in schema
        if ($token->isAnyTypeOf(TokenType::BOOL, TokenType::STRING, TokenType::FLOAT, TokenType::INT)) {
            $tokens->advance();
            return $this->consumeTypeModifiers($tokens, new LiteralType(
                $token->type->value,
                $token->coercedValue(),
            ));
        }

        if (!$token->is(TokenType::IDENTIFIER)) {
            $this->produceSyntaxError("Expected type identifier", $tokens);
        }

        // Produces an array|list type.
        if ($token->value === 'array' || $token->value === 'list') {
            // Returns the next token after the array.
            return $this->consumeArrayType($tokens);
        }

        // Produces an object.
        if ($token->value === 'object') {
            return $this->consumeStruct($tokens);
        }

        $tokens->advance();
        return $this->consumeTypeModifiers(
            $tokens,
            BuiltInType::is($token->value)
                ? new BuiltInType($token->value)
                : $this->parseCustomType($token),
        );
    }

    private function consumeStruct(Tokens $tokens): NodeInterface
    {
        if (!$tokens->currentTokenIs(TokenType::IDENTIFIER) || !$tokens->currentValueIn('object', 'array')) {
            $this->produceSyntaxError("Expected object", $tokens);
        }

        $structType = StructPhpType::from($tokens->current()->value);

        $tokens->advance();
        if (!$tokens->current()->is(TokenType::LBRACE)) {
            return new RecordType(new BuiltInType("mixed"));
        }

        $tokens->advance();
        $properties = [];

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
            $properties[$name] = $isOptional ? new OptionalType($type) : $type;

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
        return new StructType($structType, $properties);
    }

    private function consumeOptionalObjectKey(Tokens $tokens): bool
    {
        if ($tokens->current()->is(TokenType::QUESTION_MARK)) {
            $tokens->advance();
            return true;
        }
        return false;
    }

    private function consumeTypeOrUnion(Tokens $tokens, TokenType ...$stopAt): NodeInterface
    {
        $openUnion = true;
        $nullableByQuestionMark = false;

        $types = [];

        if ($tokens->currentTokenIs(TokenType::QUESTION_MARK)) {
            $nullableByQuestionMark = true;
            $tokens->advance();
            $types[] = new BuiltInType('null');
        }

        do {
            $token = $tokens->current();

            // If we reach an ending token, we stop.
            if ($token->isAnyTypeOf(TokenType::EOF, ...$stopAt)) {
                break;
            }

            if ($token->is(TokenType::PIPE)) {
                if ($openUnion) {
                    $this->produceSyntaxError("Expected Type Identifier, got Pipe", $tokens);
                }
                if ($nullableByQuestionMark) {
                    $this->produceSyntaxError("Cannot use ?type as nullable and pipe at the same time", $tokens);
                }

                $openUnion = true;
                $tokens->advance();
                continue;
            }

            $types[] = $this->consumeType($tokens);
            $openUnion = false;
        } while ($tokens->canAdvance());

        if ($openUnion) {
            $this->produceSyntaxError("Expected type Identifier", $tokens);
        }

        // Produces a union or a type
        return count($types) > 1 ? $this->checkForDiscriminatedUnion($types) : $types[0];
    }

    private function checkForDiscriminatedUnion(array $types): UnionType
    {
        // ToDo: Support other types too, like complex types.
        if (!array_all($types, fn(NodeInterface $type) => $type instanceof StructType)) {
            return new UnionType($types);
        }

        $possibleDiscriminatedFields = [];

        // ToDo: Not efficient.
        /** @var StructType $type */
        foreach ($types as $type) {
            foreach ($type->properties as $name => $property) {
                if (!$property instanceof LiteralType) {
                    continue;
                }
                $possibleDiscriminatedFields[$name][] = $property->value;
            }
        }

        foreach ($possibleDiscriminatedFields as $name => $values) {
            if (count(array_unique($values)) !== count($types)) {
                continue;
            }

            return new UnionType($types, $name, $values);
        }

        return new UnionType($types);
    }

    private function consumeArrayType(Tokens $tokens): NodeInterface
    {
        if (!$tokens->current()->is(TokenType::IDENTIFIER) || !in_array($tokens->current()->value, ['array', 'list'], true)) {
            $this->produceSyntaxError("Expected Array Type Identifier: array or list", $tokens);
        }

        // Handle array structures.
        if ($tokens->current()->value === 'array' && $tokens->nextTokenIs(TokenType::LBRACE)) {
            if ($tokens->peek(2)?->type === TokenType::INT) {
                return $this->consumeTuple($tokens);
            }

            return $this->consumeStruct($tokens);
        }

        $maxGenerics = $tokens->current()->value === 'list' ? 1 : 2;

        // Consuming of the array type identifier
        $tokens->advance();

        // No generics
        if (!$tokens->currentTokenIs(TokenType::LT)) {
            return new ListType(new BuiltInType('mixed'));
        }

        $generics = $this->consumeGenerics($tokens, min: 1, max: $maxGenerics);

        if (count($generics) === 1) {
            return new ListType($generics[0]);
        }

        $keyType = $generics[0];
        if (!$keyType instanceof BuiltInType || $keyType->type !== 'string') {
            $this->produceSyntaxError("Array key type must be 'string'. Got: {$keyType}", $tokens);
        }

        return new RecordType($generics[1]);
    }

    private function consumeTuple(Tokens $tokens): TupleType
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
        return new TupleType($types);
    }

    private function consumeGenerics(Tokens $tokens, ?int $min = null, ?int $max = null): array
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

    private function produceSyntaxError(string $message, ?Tokens $tokens): never
    {
        throw new RuntimeException(implode(PHP_EOL, array_filter([
            "Syntax Error: {$message}",
            $tokens?->current()->highlightArea($tokens->input),
        ])));
    }
}