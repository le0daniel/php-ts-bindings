<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

interface Coercible
{
    public function coerce(mixed $value): mixed;
}