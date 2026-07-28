<?php declare(strict_types=1);

namespace Tests\Mocks\ValueObjects;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;

#[Castable]
final class CreateAccountInput
{
    public Email $email;
    public UserId $ownerId;
}
