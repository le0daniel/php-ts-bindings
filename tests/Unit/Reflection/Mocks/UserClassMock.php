<?php

declare(strict_types=1);

namespace Tests\Unit\Reflection\Mocks;

final class UserClassMock
{
    /**
     * @var array{isAdmin?: bool, isSuperAdmin?: bool}
     */
    public readonly array $options;

    /**
     * @var array{
     *   street: string,
     *   city: non-empty-string,
     * }
     */
    public readonly array $address;

    /**
     * @param  non-empty-string  $name
     * @param array{
     *   theme: string,
     *   notifications: array{
     *     email: bool,
     *   },
     * } $settings
     */
    public function __construct(
        public readonly string $name,
        public \DateTimeInterface $birthdate,
        public readonly array $settings = [],
    ) {
    }

    /**
     * @return non-empty-string
     */
    public function toString(): string
    {
        throw new \Exception();
    }

    public function toArray(): array
    {
        throw new \Exception();
    }

    /**
     * @return array{
     *   id: non-empty-string,
     *   roles: list<string>,
     * }
     */
    public function serialize(): array
    {
        throw new \Exception();
    }
}
