<?php declare(strict_types=1);

namespace Tests\Unit\Parser\Data\Stubs;

readonly class AccountData
{
    public function __construct(
        public int $id,
        public string $name,
    )
    {
    }
}