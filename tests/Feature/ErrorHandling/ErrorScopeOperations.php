<?php

declare(strict_types=1);

namespace Tests\Feature\ErrorHandling;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Middleware;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use RuntimeException;

/**
 * One operation per error handling scenario: which scope declared, which scope threw, and what the
 * client is told as a result.
 */
final class ErrorScopeOperations
{
    /**
     * @param  array{value: string}  $data
     * @return array{ok: bool}
     */
    #[Command('errors')]
    #[Throws(TeapotException::class, name: 'teapot')]
    public function throwsDeclaredDomainError(array $data): array
    {
        throw new TeapotException();
    }

    /**
     * The middleware throws before this handler ever runs; the declaration below belongs to the
     * handler's scope and must not cover the middleware's throw.
     *
     * @param  array{value: string}  $data
     * @return array{ok: bool}
     */
    #[Command('errors')]
    #[Middleware(UndeclaredThrowingMiddleware::class)]
    #[Throws(SharedException::class, name: 'shared')]
    public function declaresButMiddlewareThrows(array $data): array
    {
        return ['ok' => true];
    }

    /**
     * @param  array{value: string}  $data
     * @return array{ok: bool}
     */
    #[Command('errors')]
    #[Middleware(DecoyDeclaringMiddleware::class)]
    public function throwsWhatOnlyMiddlewareDeclares(array $data): array
    {
        throw new SharedException();
    }

    /**
     * @param  array{value: string}  $data
     * @return array{ok: bool}
     */
    #[Command('errors')]
    #[Middleware(SelfDeclaringMiddleware::class)]
    public function middlewareOwnDomainError(array $data): array
    {
        return ['ok' => true];
    }

    /**
     * @param  array{value: string}  $data
     * @return array{ok: bool}
     */
    #[Command('errors')]
    #[Throws(MissingResourceException::class, type: ErrorType::NOT_FOUND)]
    public function throwsMappedNotFound(array $data): array
    {
        throw new MissingResourceException();
    }

    /**
     * @param  array{value: string}  $data
     * @return array{ok: bool}
     */
    #[Command('errors')]
    public function throwsUnclassified(array $data): array
    {
        throw match ($data['value']) {
            'unauthenticated' => new SessionExpiredException(),
            'unauthenticated-subclass' => new TokenExpiredException(),
            'unauthorized' => new ForbiddenException(),
            'not-found' => new GoneException(),
            default => new RuntimeException('plain boom'),
        };
    }

    /**
     * @param  array{value: string}  $data
     * @return array{ok: bool}
     */
    #[Command('errors')]
    #[Throws(ConflictException::class, name: 'conflict')]
    public function throwsDeclaredAndConfigured(array $data): array
    {
        throw new ConflictException();
    }
}
