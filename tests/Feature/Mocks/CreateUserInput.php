<?php declare(strict_types=1);

namespace Tests\Feature\Mocks;

final class CreateUserInput
{
    public string $username;

    /**
     * @var positive-int
     */
    public int $age;

    public string $email;
}