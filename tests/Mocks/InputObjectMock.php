<?php declare(strict_types=1);

namespace Tests\Mocks;

use Tests\Mocks\Users\UserMock as BaseUser;

final class InputObjectMock
{
    /**
     * @param string $name
     * @param int $age
     * @param array<BaseUser> $object
     */
    public function __construct(
        public string $name,
        public int $age,
        public array $object,
    )
    {
    }
}