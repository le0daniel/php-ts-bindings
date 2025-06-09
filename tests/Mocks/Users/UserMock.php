<?php declare(strict_types=1);

namespace Tests\Mocks\Users;

final class UserMock
{
    public function __construct(
        public string $username,
        public int $age,
        public string $email,
    ) {}
}