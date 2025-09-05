<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;

final readonly class Operation
{
    /**
     * @param string $key
     * @param Definition $definition
     * @param NodeInterface|Closure(): NodeInterface $input
     * @param NodeInterface|Closure(): NodeInterface $output
     */
    public function __construct(
        public string                 $key,
        public Definition             $definition,
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
}