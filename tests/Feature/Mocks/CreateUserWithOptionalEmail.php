<?php declare(strict_types=1);

namespace Tests\Feature\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Optional;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;

#[Castable(ObjectCastStrategy::CONSTRUCTOR)]
final readonly class CreateUserWithOptionalEmail
{
    public function __construct(
        public string $username,

        #[Optional]
        public null|string $email = null,
    )
    {
    }
}