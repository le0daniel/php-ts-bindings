<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

final readonly class ParsingOptions
{
    public function __construct(
        public bool $partialFailures = false,
        public bool $coercePrimitives = false,
    )
    {
    }
}