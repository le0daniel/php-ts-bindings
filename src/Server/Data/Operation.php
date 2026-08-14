<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Closure;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;

final class Operation
{
    private ?NodeInterface $inputNode = null;
    private ?NodeInterface $outputNode = null;

    /**
     * @var Closure(): NodeInterface|null
     */
    private readonly Closure|null $inputNodeFactory;

    /**
     * @var Closure(): NodeInterface|null
     */
    private readonly Closure|null $outputNodeFactory;

    /**
     * @param  NodeInterface|Closure(): NodeInterface  $input
     * @param  NodeInterface|Closure(): NodeInterface  $output
     */
    public function __construct(
        public readonly string $key,
        public readonly Definition $definition,
        NodeInterface|Closure $input,
        NodeInterface|Closure $output,
    ) {
        if ($input instanceof NodeInterface) {
            $this->inputNode = $input;
            $this->inputNodeFactory = null;
        } else {
            $this->inputNodeFactory = $input;
        }

        if ($output instanceof NodeInterface) {
            $this->outputNode = $output;
            $this->outputNodeFactory = null;
        } else {
            $this->outputNodeFactory = $output;
        }
    }

    public function inputNode(): NodeInterface
    {
        /** @phpstan-ignore-next-line */
        return $this->inputNode ??= ($this->inputNodeFactory)();
    }

    public function outputNode(): NodeInterface
    {
        /** @phpstan-ignore-next-line */
        return $this->outputNode ??= ($this->outputNodeFactory)();
    }
}
