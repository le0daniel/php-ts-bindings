<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Definition;

final class Position
{
    public function __construct(
        public int $line,
        public int $offset,
    )
    {
    }
}