<?php

declare(strict_types=1);

namespace Tests\Unit\Server\Errors\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Tests\Mocks\Errors\RecordMissingException;
use Tests\Mocks\Errors\UnexposedException;

/**
 * One method per resolveReflection scenario: the tests reflect these methods directly,
 * so only the #[Throws] attributes matter.
 */
final class ThrowResolverOperations
{
    public function declaresNothing(): void
    {
    }

    #[Throws(UnexposedException::class, ErrorType::NOT_FOUND)]
    public function declaresExplicitNotFound(): void
    {
    }

    #[Throws(UnexposedException::class, name: 'direct_name')]
    public function declaresNamedDomainError(): void
    {
    }

    #[Throws(UnexposedException::class, ErrorType::DOMAIN_ERROR, name: 'explicit_domain')]
    public function declaresExplicitDomainWithName(): void
    {
    }

    #[Throws(NotFoundExposedException::class)]
    public function declaresViaExposeAsNotFound(): void
    {
    }

    #[Throws(NamedDomainExposedException::class)]
    public function declaresViaExposeAsNamedDomain(): void
    {
    }

    #[Throws(UnexposedException::class)]
    public function declaresUnexposed(): void
    {
    }

    #[Throws(InvalidExposeAsException::class)]
    public function declaresInvalidExposeAs(): void
    {
    }

    #[Throws(UnexposedException::class, ErrorType::DOMAIN_ERROR)]
    public function declaresDomainWithoutName(): void
    {
    }

    #[Throws(UnexposedException::class, ErrorType::NOT_FOUND, name: 'nope')]
    public function declaresNamedNotFound(): void
    {
    }

    #[Throws(UnexposedException::class, ErrorType::INVALID_INPUT)]
    public function declaresInvalidInput(): void
    {
    }

    #[Throws(UnexposedException::class, ErrorType::NOT_FOUND)]
    #[Throws(UnexposedException::class, name: 'second')]
    public function declaresDuplicate(): void
    {
    }

    #[Throws(UnexposedException::class, ErrorType::DOMAIN_ERROR)]
    #[Throws(UnexposedException::class, ErrorType::NOT_FOUND)]
    public function declaresDuplicateAfterInvalid(): void
    {
    }

    #[Throws(UnexposedException::class, ErrorType::NOT_FOUND)]
    #[Throws(NotFoundExposedException::class, ErrorType::DOMAIN_ERROR)]
    #[Throws(NamedDomainExposedException::class)]
    #[Throws(RecordMissingException::class)]
    public function declaresMixed(): void
    {
    }

    // @phpstan-ignore-next-line -- the test pins that an unresolvable class escapes as a ReflectionException.
    #[Throws('Tests\Unit\Server\Errors\Mocks\DoesNotExist')]
    public function declaresNonexistentClass(): void
    {
    }
}
