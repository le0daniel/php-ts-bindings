<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

final readonly class SerializationOptions
{
    public function __construct(
        public bool $partialFailures = true,
        public bool $runConstraints = false,
        public int  $maxDepth = 64,
        public int  $maxItems = 10_000,
    )
    {
    }
}