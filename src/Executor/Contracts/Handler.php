<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Contracts;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Executor\Data\Context;

/**
 * @template-covariant T of NodeInterface
 */
interface Handler
{
    public function serialize(NodeInterface $node, mixed $value, Context $context, Executor $executor): mixed;

    public function parse(NodeInterface $node, mixed $value, Context $context, Executor $executor): mixed;
}