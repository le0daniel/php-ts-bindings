<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Operations\Data;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Throwable;

final readonly class Endpoint
{
    /**
     * @param OperationDefinition $definition
     * @param NodeInterface|Closure(): NodeInterface $input
     * @param NodeInterface|Closure(): NodeInterface $output
     */
    public function __construct(
        public OperationDefinition    $definition,
        private NodeInterface|Closure $input,
        private NodeInterface|Closure $output,
    )
    {
    }

    public function inputNode(): NodeInterface
    {
        return $this->input instanceof Closure ? ($this->input)() : $this->input;
    }

    public function outputNode(): NodeInterface
    {
        return $this->output instanceof Closure ? ($this->output)() : $this->output;
    }

    public function isHandledException(Throwable $exception): bool
    {
        return in_array($exception::class, $this->definition->caughtExceptions, true);
    }
}