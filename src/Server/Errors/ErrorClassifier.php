<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Errors;

use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidInputException;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\OperationNotFoundException;
use Throwable;

final readonly class ErrorClassifier
{
    /**
     * @param list<class-string> $authenticationExceptions
     * @param list<class-string> $authorizationExceptions
     * @param list<class-string> $notFoundExceptions
     */
    public function __construct(
        public array $authenticationExceptions,
        public array $authorizationExceptions,
        public array $notFoundExceptions,
    ) {
    }

    /**
     * @param Throwable|class-string<Throwable> $exception
     * @return ErrorType
     */
    public function classify(Throwable|string $exception): ErrorType
    {
        $className = is_string($exception) ? $exception : $exception::class;

        return match (true) {
            // Exact match for invalid input
            $className === InvalidInputException::class => ErrorType::INVALID_INPUT,
            $className === OperationNotFoundException::class => ErrorType::NOT_FOUND,
            $this->matchesAny($className, $this->authenticationExceptions) => ErrorType::AUTHENTICATION_ERROR,
            $this->matchesAny($className, $this->authorizationExceptions) => ErrorType::AUTHORIZATION_ERROR,
            $this->matchesAny($className, $this->notFoundExceptions) => ErrorType::NOT_FOUND,
            default => ErrorType::INTERNAL_ERROR,
        };
    }

    /**
     * @param class-string<Throwable> $className
     * @param list<class-string> $exceptions
     * @return bool
     */
    private function matchesAny(string $className, array $exceptions): bool
    {
        return array_any(
            $exceptions,
            static fn ($exception) => is_a($className, $exception, true)
        );
    }
}
