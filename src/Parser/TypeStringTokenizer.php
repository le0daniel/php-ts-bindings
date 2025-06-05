<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser;

use RuntimeException;

final class TypeStringTokenizer
{
    /**
     * @param string $typeString
     * @return Tokens
     */
    public function tokenize(string $typeString): Tokens
    {
        $currentOffset = 0;
        $length = strlen($typeString);

        /** @var array<Token> $tokens */
        $tokens = [];
        $buffer = "";

        /** @var null|TokenType $blockType */
        $blockType = null;

        while ($currentOffset < $length) {
            $char = $typeString[$currentOffset];

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
                    $currentOffset - strlen($buffer) - 2,
                    $currentOffset,
                );
                $buffer = '';
                $blockType = null;
                continue;
            }

            $identifiedToken = $this->identifyBreakingTokenType($char, $typeString[$currentOffset + 1] ?? null);
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
                    $currentOffset - strlen($buffer),
                    $currentOffset,
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
                $currentOffset,
                $currentOffset + $tokenLength,
            );

            $currentOffset += $tokenLength;
        }

        if ($blockType) {
            throw new RuntimeException("Unclosed block type: {$blockType->value}");
        }

        if (!empty($buffer)) {
            $tokens[] = new Token(
                $this->determineBufferedTokenType($buffer),
                $buffer,
                $currentOffset - strlen($buffer),
                $currentOffset,
            );
        }

        $tokens[] = new Token(
            TokenType::EOF,
            '',
            $currentOffset,
            $currentOffset,
        );

        return new Tokens($typeString, $tokens);
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
            TokenType::PIPE->value => TokenType::PIPE,
            TokenType::LT->value => TokenType::LT,
            TokenType::GT->value => TokenType::GT,
            TokenType::COMMA->value => TokenType::COMMA,
            TokenType::LBRACE->value => TokenType::LBRACE,
            TokenType::RBRACE->value => TokenType::RBRACE,
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

        return TokenType::IDENTIFIER;
    }

}