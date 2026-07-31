<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Contracts;

interface WrapsNode
{
    public NodeInterface $node {
        get;
    }
}