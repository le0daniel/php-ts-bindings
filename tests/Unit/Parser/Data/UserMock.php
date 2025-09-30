<?php declare(strict_types=1);

namespace Tests\Unit\Parser\Data;

final class UserMock
{
    public function __construct(
        public string $username,
        public int $age,
        public string $email,
    ) {}
}