<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Mocks;

final class ReflectionsUtilMock
{
    /**
     * @param  array{amount: string, birthdate: \DateTime}  $age
     * @param  object{name: string, other: string}  $others
     * @param array{
     *   theme: string,
     *   notifications: array{
     *     email: bool,
     *   },
     * } $settings
     */
    public function __construct(
        public string $name,
        public array $age,
        object $others,
        public array $settings = [],
    ) {
    }

    /**
     * @return array{string, int}
     */
    public function serialize(): array
    {
        return ['', 1];
    }

    /**
     * @return array{
     *   id: non-empty-string,
     *   roles: list<string>,
     * }
     */
    public function serializeDeeply(): array
    {
        return ['id' => 'id', 'roles' => []];
    }
}
