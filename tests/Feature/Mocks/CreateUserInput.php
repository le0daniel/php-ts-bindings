<?php declare(strict_types=1);

namespace Tests\Feature\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;
use Le0daniel\PhpTsBindings\Validators\Email;

#[Castable]
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