<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

interface TypeConsumer
{
    public function canConsume(ParserState $state): bool;
    public function consume(ParserState $state, TypeParser $parser): NodeInterface;
}