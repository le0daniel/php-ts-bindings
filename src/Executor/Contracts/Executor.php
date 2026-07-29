<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Contracts;

use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;

interface Executor
{
    public function executeSerialize(NodeInterface $node, mixed $data, Context $context): mixed;
    public function executeParse(NodeInterface $node, mixed $data, Context $context): mixed;
}