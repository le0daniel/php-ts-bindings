<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Contracts;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;

interface TypeRegistry
{
    public function get(string $fullyQualifiedClassName): NodeInterface;
}