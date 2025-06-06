<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Parsers;

use DateTimeInterface;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Contracts\Parser;
use Le0daniel\PhpTsBindings\Parser\Definition\Token;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\DateTimeNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

final class DateTimeParser implements Parser
{

    public function canParse(Token $token): bool
    {
        if (!$token->is(TokenType::IDENTIFIER)) {
            return false;
        }

        return is_a($token->fullyQualifiedValue, DateTimeInterface::class, true);
    }

    public function parse(Token $token, TypeParser $parser): NodeInterface
    {
        return new DateTimeNode($token->fullyQualifiedValue);
    }
}