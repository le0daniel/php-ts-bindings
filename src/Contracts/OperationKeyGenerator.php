<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

interface OperationKeyGenerator
{
    /**
     * The key must be a pure function of namespace and name: the Preloader re-derives keys from
     * exactly these two values, without a Definition in hand. That is why this method deliberately
     * does not receive the full Definition - an implementation keying off class or method names
     * would produce keys the Preloader can never reconstruct.
     */
    public function generateKey(string $namespace, string $name): string;
}
