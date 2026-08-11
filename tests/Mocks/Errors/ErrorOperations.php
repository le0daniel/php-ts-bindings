<?php

declare(strict_types=1);

namespace Tests\Mocks\Errors;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;

/**
 * Not discovered through the registry: the tests build a Definition by hand and point it at these
 * methods, so only the #[Throws] attributes matter.
 */
final class ErrorOperations
{
    #[Throws(ExposedDomainException::class)]
    #[Throws(UnexposedException::class)]
    public function declaresThrows(): void
    {
    }

    /**
     * Naming at the declaration site: UnexposedException carries no #[ExposeAs] and is exposed
     * anyway, while ExposedDomainException's own name is overridden.
     */
    #[Throws(UnexposedException::class, name: 'renamed_failure')]
    #[Throws(ExposedDomainException::class, name: 'overridden_failure')]
    public function declaresRenamedThrows(): void
    {
    }

    public function declaresNothing(): void
    {
    }
}
