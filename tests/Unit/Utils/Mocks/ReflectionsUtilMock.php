<?php declare(strict_types=1);

namespace Tests\Unit\Utils\Mocks;

final class ReflectionsUtilMock
{
    /**
     * @param string $name
     * @param array{amount: string, birthdate: \DateTime} $age
     * @param object{name: string, other: string} $others
     */
    public function __construct(
        public string $name,
        public array $age,
        object $others
    )
    {
    }

    /**
     * @return array{string, int}
     */
    public function serialize(): array
    {
        return ["", 1];
    }
}