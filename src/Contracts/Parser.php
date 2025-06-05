<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

use Le0daniel\PhpTsBindings\Parser\Token;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

interface Parser
{
    public function canParse(Token $token): bool;
    public function parse(Token $token, TypeParser $parser): NodeInterface;
}