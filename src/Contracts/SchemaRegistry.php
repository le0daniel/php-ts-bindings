<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

interface SchemaRegistry
{
    public function get(string $fullyQualifiedClassName): NodeInterface;
}