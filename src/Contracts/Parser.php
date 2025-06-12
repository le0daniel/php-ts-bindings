<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

use Le0daniel\PhpTsBindings\Parser\Definition\Token;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

interface Parser
{
    public function canParse(string $fullyQualifiedClassName, Token $token): bool;
    public function parse(string $fullyQualifiedClassName, Token $token, TypeParser $parser): NodeInterface;
}