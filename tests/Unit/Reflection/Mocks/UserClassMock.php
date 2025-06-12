<?php declare(strict_types=1);

namespace Tests\Unit\Reflection\Mocks;

final class UserClassMock
{
    /**
     * @var array{isAdmin?: bool, isSuperAdmin?: bool}
     */
    public readonly array $options;

    /**
     * @param non-empty-string $name
     */
    public function __construct(
        public readonly string $name,
        public \DateTimeInterface $birthdate,
    )
    {
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
}