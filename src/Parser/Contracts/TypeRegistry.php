<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Contracts;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;

/** @internal This is only used when optimizing AST's */
interface TypeRegistry
{
    public function get(string $fullyQualifiedClassName): NodeInterface;
}