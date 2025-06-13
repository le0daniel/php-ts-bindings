<?php declare(strict_types=1);

namespace Tests\Feature\Mocks;

use Le0daniel\PhpTsBindings\Validators\Email;
use Le0daniel\PhpTsBindings\Validators\NonEmptyString;

final class CreateUserInput
{
    public string $username;

    /**
     * @var positive-int
     */
    public int $age;

    #[Email]
    public string $email;
}