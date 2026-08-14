<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Contracts;

use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;

/**
 * @template-covariant T of NodeInterface
 */
interface Handler
{
    public function serialize(NodeInterface $node, mixed $value, Context $context, Executor $executor): mixed;

    public function parse(NodeInterface $node, mixed $value, Context $context, Executor $executor): mixed;
}
