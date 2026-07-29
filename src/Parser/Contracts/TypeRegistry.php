<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Contracts;

/** @internal This is only used when optimizing AST's */
interface TypeRegistry
{
    public function get(string $key): NodeInterface;
}