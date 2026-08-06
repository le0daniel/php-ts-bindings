<?php

declare(strict_types=1);

namespace Tests\Unit\Parser\Data\Stubs;

final class UncastableClass
{
    public function __construct(
        public string $email,
        public string $name,
    ) {
    }
}
