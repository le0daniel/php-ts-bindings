<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Contracts;

interface Coercible
{
    public function coerce(mixed $value): mixed;
}
