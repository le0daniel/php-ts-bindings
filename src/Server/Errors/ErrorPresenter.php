<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Errors;

use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidInputException;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\OperationNotFoundException;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Throwable;

/**
 * Turns a Throwable into the RpcError the client sees.
 *
 * The catalogue below is finite and closed: it is what the server needs to run, not an extension
 * point. What an application configures is which of its exceptions belong in which category, via
 * ServerConfiguration::withExceptions(). Everything unrecognised is an internal error - an
 * exception only reaches the client on purpose, never by accident.
 *
 * Resolution is top to bottom and the first match wins, so the exposed domain error sits last,
 * just before the catch all: an exception that is explicitly categorised stays categorised even
 * when it is named by #[Throws(..., as: ...)] or carries #[ExposeAs].
 *
 * The TypeScript counterpart of this catalogue lives in CodeGen\Utils\ErrorTypescript.
 */
final readonly class ErrorPresenter
{
    public function __construct(
        private ServerConfiguration $configuration,
    )
    {
    }

    /**
     * Total: every Throwable yields an RpcError, including one thrown while working out what to
     * present.
     *
     * $definition is null when there is no operation to speak of (an unknown name), which also
     * means there is nothing to reflect on for the domain error case.
     */
    public function present(Throwable $throwable, ?Definition $definition, ?ResolveInfo $info): RpcError
    {
        try {
            [$type, $details] = $this->resolve($throwable, $definition);
            return new RpcError($type, $throwable, $details, $info);
        } catch (Throwable $presentationFailure) {
            // Losing this one is expensive to debug: a stale middleware class name makes
            // ExposedExceptions throw, and from then on every exception from the operation
            // degrades to an internal error with no #[Throws] mapping ever applying again, with
            // nothing anywhere saying why. $cause stays the exception the application threw; the
            // presentation failure rides alongside it for the reporter.
            return self::internalError($throwable, $info, $presentationFailure);
        }
    }

    /**
     * The last resort shape, for when presenting itself fails.
     */
    public static function internalError(
        Throwable  $throwable,
        ?ResolveInfo $info,
        ?Throwable $presentationFailure = null,
    ): RpcError
    {
        return new RpcError(
            ErrorType::INTERNAL_ERROR,
            $throwable,
            ['type' => 'INTERNAL_SERVER_ERROR'],
            $info,
            presentationFailure: $presentationFailure,
        );
    }

    /**
     * @return array{ErrorType, array<string, mixed>}
     */
    private function resolve(Throwable $throwable, ?Definition $definition): array
    {
        if ($throwable instanceof InvalidInputException) {
            return [ErrorType::INVALID_INPUT, [
                'type' => 'INVALID_INPUT',
                'fields' => $throwable->failure->issues->serializeToFieldsArray(),
            ]];
        }

        if ($this->matchesAny($throwable, $this->configuration->unauthenticatedExceptions)) {
            return [ErrorType::AUTHENTICATION_ERROR, ['type' => 'UNAUTHENTICATED']];
        }

        if ($this->matchesAny($throwable, $this->configuration->unauthorizedExceptions)) {
            return [ErrorType::AUTHORIZATION_ERROR, ['type' => 'UNAUTHORIZED']];
        }

        if ($throwable instanceof OperationNotFoundException || $this->matchesAny($throwable, $this->configuration->notFoundExceptions)) {
            return [ErrorType::NOT_FOUND, ['type' => 'NOT_FOUND']];
        }

        if ($definition && $exposedType = $this->exposedTypeOf($throwable, $definition)) {
            return [ErrorType::DOMAIN_ERROR, ['type' => $exposedType]];
        }

        return [ErrorType::INTERNAL_ERROR, ['type' => 'INTERNAL_SERVER_ERROR']];
    }

    /**
     * @param list<class-string<Throwable>> $classNames
     */
    private function matchesAny(Throwable $throwable, array $classNames): bool
    {
        return array_any($classNames, static fn(string $className): bool => $throwable instanceof $className);
    }

    /**
     * An exception is a domain error only if the operation declares it via #[Throws] and that
     * declaration resolves to a name - from its own `as`, or from #[ExposeAs] on the exception.
     * Declared but unnamed is null, and falls through to the catch all.
     */
    private function exposedTypeOf(Throwable $throwable, Definition $definition): ?string
    {
        return ExposedExceptions::declaredFor($definition, $this->configuration)[$throwable::class] ?? null;
    }
}
