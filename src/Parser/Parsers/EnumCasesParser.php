<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Parsers;

use Le0daniel\PhpTsBindings\Contracts\Parser;
use Le0daniel\PhpTsBindings\Parser\Definition\Token;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\EnumNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use UnitEnum;

final class EnumCasesParser implements Parser
{
    public function canParse(string $fullyQualifiedClassName, Token $token): bool
    {
        return enum_exists($fullyQualifiedClassName);
    }

    public function parse(string $fullyQualifiedClassName, Token $token): EnumNode
    {
        /** @var class-string<UnitEnum> $fullyQualifiedClassName */
        return new EnumNode($fullyQualifiedClassName);
    }
}
