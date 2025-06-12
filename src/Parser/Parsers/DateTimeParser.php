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

    public function canParse(string $fullyQualifiedClassName, Token $token): bool
    {
        return is_a($fullyQualifiedClassName, DateTimeInterface::class, true);
    }

    public function parse(string $fullyQualifiedClassName, Token $token, TypeParser $parser): NodeInterface
    {
        return new DateTimeNode($fullyQualifiedClassName);
    }
}