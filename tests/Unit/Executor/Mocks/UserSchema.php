<?php declare(strict_types=1);

namespace Tests\Unit\Executor\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;

#[Castable]
final readonly class UserSchema
{
    public function __construct(
        public int       $age,
        protected string $email,
        public string    $username,
    )
    {
    }
}