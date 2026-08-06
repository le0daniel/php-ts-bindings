<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Contracts;

interface WrapsNodes
{
    /**
     * @var list<NodeInterface>
     */
    public array $nodes {
        get;
    }
}
