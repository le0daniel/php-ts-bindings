<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser;

use Le0daniel\PhpTsBindings\Parser\Definition\Position;
use Le0daniel\PhpTsBindings\Parser\Definition\Token;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use RuntimeException;

final class TypeStringTokenizer
{
    /**
     * @param string $typeString
     * @return non-empty-list<Token>
     */
    public function tokenize(string $typeString): array
    {
        $currentOffset = 0;
        $length = strlen($typeString);

        /** @var list<Token> $tokens */
        $tokens = [];
        $buffer = "";

        /** @var null|TokenType $blockType */
        $blockType = null;

        while ($currentOffset < $length) {
            $char = $typeString[$currentOffset];

            if ($blockType === TokenType::CLASS_CONST) {
                if (preg_match('/^[a-zA-Z0-9_]+$/', $char) === 1) {
                    $buffer .= $char;
                    $currentOffset++;
                    continue;
                }

                $tokens[] = new Token(
                    TokenType::CLASS_CONST,
                    $buffer,
                    new Position(0, $currentOffset - strlen($buffer)),
                    new Position(0, $currentOffset),
                );
                $buffer = '';
                $blockType = null;
            }

            // We are not in a token itself
            if ($blockType) {
                $currentOffset++;
                // Buffer in block type
                if (!$this->isEndingQuote($blockType, $char)) {
                    $buffer .= $char;
                    continue;
                }

                $tokens[] = new Token(
                    TokenType::STRING,
                    $buffer,
                    new Position(0, $currentOffset - strlen($buffer) - 2),
                    new Position(0, $currentOffset),
                );
                $buffer = '';
                $blockType = null;
                continue;
            }

            $identifiedToken = $this->identifyBreakingTokenType($char, $typeString[$currentOffset + 1] ?? null);
            // Handle tokenization of "Value::CONST"
            if ($identifiedToken === TokenType::DOUBLE_COLON && ctype_alnum($typeString[$currentOffset + 2]) && $buffer !== '') {
                $blockType = TokenType::CLASS_CONST;
                $buffer .= "::";
                $currentOffset += 2;
                continue;
            }

            if ($identifiedToken === null) {
                $buffer .= $char;
                $currentOffset++;
                continue;
            }

            // Flush buffer first
            if ($buffer !== '') {
                $tokens[] = new Token(
                    $this->determineBufferedTokenType($buffer),
                    $buffer,
                    new Position(0, $currentOffset - strlen($buffer)),
                    new Position(0, $currentOffset),
                );
                $buffer = '';
            }

            if ($identifiedToken === TokenType::SINGLE_QUOTE || $identifiedToken === TokenType::DOUBLE_QUOTE) {
                $blockType = $identifiedToken;
                $currentOffset++;
                continue;
            }

            if ($identifiedToken === TokenType::WHITESPACE) {
                $currentOffset++;
                continue;
            }

            // Add the identified token
            $tokenLength = strlen($identifiedToken->value);
            $tokens[] = new Token(
                $identifiedToken,
                $identifiedToken->value,
                new Position(0, $currentOffset),
                new Position(0, $currentOffset + $tokenLength),
            );

            $currentOffset += $tokenLength;
        }

        if ($blockType === TokenType::SINGLE_QUOTE || $blockType === TokenType::DOUBLE_QUOTE) {
            throw new RuntimeException("Unclosed block type: {$blockType->value}");
        }

        if (!empty($buffer)) {
            $tokens[] = new Token(
                $this->determineBufferedTokenType($buffer),
                $buffer,
                new Position(0, $currentOffset - strlen($buffer)),
                new Position(0, $currentOffset),
            );
        }

        $tokens[] = new Token(
            TokenType::EOF,
            '',
            new Position(0, $currentOffset),
            new Position(0, $currentOffset),
        );

        return $tokens;
    }

    private function isEndingQuote(TokenType $endingType, string $character): bool
    {
        return match ($character) {
            TokenType::SINGLE_QUOTE->value => TokenType::SINGLE_QUOTE === $endingType,
            TokenType::DOUBLE_QUOTE->value => TokenType::DOUBLE_QUOTE === $endingType,
            default => false,
        };
    }


    private function identifyBreakingTokenType(string $character, ?string $nextToken): TokenType|null
    {
        if (ctype_space($character)) {
            return TokenType::WHITESPACE;
        }

        $singleMatch = match ($character) {
            TokenType::AND->value => TokenType::AND,
            TokenType::PIPE->value => TokenType::PIPE,
            TokenType::LT->value => TokenType::LT,
            TokenType::GT->value => TokenType::GT,
            TokenType::COMMA->value => TokenType::COMMA,
            TokenType::LBRACE->value => TokenType::LBRACE,
            TokenType::RBRACE->value => TokenType::RBRACE,
            TokenType::LPAREN->value => TokenType::LPAREN,
            TokenType::RPAREN->value => TokenType::RPAREN,
            TokenType::SINGLE_QUOTE->value => TokenType::SINGLE_QUOTE,
            TokenType::DOUBLE_QUOTE->value => TokenType::DOUBLE_QUOTE,
            TokenType::LBRACKET->value => TokenType::LBRACKET,
            TokenType::RBRACKET->value => TokenType::RBRACKET,
            TokenType::COLON->value => TokenType::COLON,
            TokenType::QUESTION_MARK->value => TokenType::QUESTION_MARK,
            default => null,
        };

        return match ($singleMatch) {
            TokenType::LBRACKET => $nextToken === TokenType::RBRACKET->value ? TokenType::CLOSED_BRACKETS : TokenType::LBRACKET,
            TokenType::COLON => $nextToken === TokenType::COLON->value ? TokenType::DOUBLE_COLON : TokenType::COLON,
            default => $singleMatch,
        };
    }

    private function determineBufferedTokenType(string $characters): TokenType
    {
        if (filter_var($characters, FILTER_VALIDATE_INT) !== false) {
            return TokenType::INT;
        }

        if (filter_var($characters, FILTER_VALIDATE_FLOAT) !== false) {
            return TokenType::FLOAT;
        }

        if ($characters === 'true' || $characters === 'false') {
            return TokenType::BOOL;
        }

        if (preg_match('/^[a-zA-Z0-9\\\_]+::[a-zA-Z0-9_]+$/', $characters) === 1) {
            return TokenType::CLASS_CONST;
        }

        return TokenType::IDENTIFIER;
    }

}