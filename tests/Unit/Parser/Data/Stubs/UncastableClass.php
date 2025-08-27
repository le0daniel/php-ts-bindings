<?php declare(strict_types=1);

namespace Tests\Unit\Parser\Data\Stubs;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;

final class UncastableClass
{
    public function __construct(
        public string $email,
        public string $name,
    )
    {
    }
}