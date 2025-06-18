<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

interface TypeConsumer
{
    public function canConsume(ParserState $tokens): bool;
    public function consume(ParserState $tokens, TypeParser $parser): ParserState;
}