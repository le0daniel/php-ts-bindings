<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Data;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Throwable;

final readonly class Endpoint
{
    public function __construct(
        public OperationDefinition $definition,
        public NodeInterface       $input,
        public NodeInterface       $output,
    )
    {
    }

    public function isHandledException(Throwable $exception): bool
    {
        return in_array($exception::class, $this->definition->caughtExceptions, true);
    }
}