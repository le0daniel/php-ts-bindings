<?php

declare(strict_types=1);

namespace Tests\Feature\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;

#[Castable]
final class CreateUserInput
{
    public string $username;

    /**
     * @var positive-int
     */
    public int $age;

    /**
     * A property is refined by its PHPStan type or not at all - there is no attribute channel.
     *
     * @var non-empty-string
     */
    public string $email;
}
