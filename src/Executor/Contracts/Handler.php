<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Contracts;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Executor\Data\Context;

/**
 * @template-covariant T of NodeInterface
 */
interface Handler
{
    /** @param NodeInterface $node */
    public function serialize(NodeInterface $node, mixed $value, Context $context, Executor $executor): mixed;

    /** @param NodeInterface $node */
    public function parse(NodeInterface $node, mixed $value, Context $context, Executor $executor): mixed;
}