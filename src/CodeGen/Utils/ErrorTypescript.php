<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Utils;

use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Server\Errors\ExposedExceptions;
use ReflectionException;

/**
 * The TypeScript face of the server's finite error catalogue.
 *
 * The runtime counterpart is Server\Errors\ErrorPresenter, and the branches below appear in its
 * resolution order. Only reachable branches are emitted: the two auth categories exist solely
 * because exceptions were mapped onto them, and a domain error only exists where an operation
 * declares an exception via #[Throws] that resolves to a name - its own `as`, or #[ExposeAs] on the
 * exception class. Everything else the server produces on its own.
 */
final readonly class ErrorTypescript
{
    private const string INVALID_INPUT_DETAILS = '{fields: Record<string, string[]>}';

    /**
     * @throws ReflectionException
     */
    public static function forOperation(ServerConfiguration $configuration, Definition $definition): string
    {
        $branches = [
            self::branch(ErrorType::INVALID_INPUT, self::INVALID_INPUT_DETAILS),
        ];

        if (count($configuration->unauthenticatedExceptions) !== 0) {
            $branches[] = self::branch(ErrorType::AUTHENTICATION_ERROR);
        }

        if (count($configuration->unauthorizedExceptions) !== 0) {
            $branches[] = self::branch(ErrorType::AUTHORIZATION_ERROR);
        }

        $branches[] = self::branch(ErrorType::NOT_FOUND);

        if ($domainDetails = self::domainDetails($configuration, $definition)) {
            $branches[] = self::branch(ErrorType::DOMAIN_ERROR, $domainDetails);
        }

        $branches[] = self::branch(ErrorType::INTERNAL_ERROR);

        return implode('|', $branches);
    }

    /**
     * @throws ReflectionException
     */
    private static function domainDetails(ServerConfiguration $configuration, Definition $definition): ?string
    {
        $exposedTypes = ExposedExceptions::exposedTypesFor($definition, $configuration);
        if (count($exposedTypes) === 0) {
            return null;
        }

        return implode('|', array_map(static function (string $exposedType): string {
            $type = json_encode($exposedType, JSON_THROW_ON_ERROR);
            return "{type: {$type}}";
        }, $exposedTypes));
    }

    /**
     * No `details` at all where the category is the whole answer: the server omits the key rather
     * than restate the type under it, and the branch has to say so or narrowing on `type` would
     * hand back a property that is never on the wire.
     */
    private static function branch(ErrorType $type, ?string $details = null): string
    {
        $name = json_encode($type->name, JSON_THROW_ON_ERROR);
        return $details === null
            ? "{code: {$type->value}, type: {$name}}"
            : "{code: {$type->value}, type: {$name}, details: {$details}}";
    }
}
