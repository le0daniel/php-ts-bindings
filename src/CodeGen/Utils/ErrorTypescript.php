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
    private const string INVALID_INPUT_DETAILS = '{type: "INVALID_INPUT"; fields: Record<string, string[]>}';
    private const string UNAUTHENTICATED_DETAILS = '{type: "UNAUTHENTICATED"}';
    private const string UNAUTHORIZED_DETAILS = '{type: "UNAUTHORIZED"}';
    private const string NOT_FOUND_DETAILS = '{type: "NOT_FOUND"}';
    private const string INTERNAL_ERROR_DETAILS = '{type: "INTERNAL_SERVER_ERROR"}';

    /**
     * @throws ReflectionException
     */
    public static function forOperation(ServerConfiguration $configuration, Definition $definition): string
    {
        $branches = [
            self::branch(ErrorType::INVALID_INPUT, self::INVALID_INPUT_DETAILS),
        ];

        if (!empty($configuration->unauthenticatedExceptions)) {
            $branches[] = self::branch(ErrorType::AUTHENTICATION_ERROR, self::UNAUTHENTICATED_DETAILS);
        }

        if (!empty($configuration->unauthorizedExceptions)) {
            $branches[] = self::branch(ErrorType::AUTHORIZATION_ERROR, self::UNAUTHORIZED_DETAILS);
        }

        $branches[] = self::branch(ErrorType::NOT_FOUND, self::NOT_FOUND_DETAILS);

        if ($domainDetails = self::domainDetails($configuration, $definition)) {
            $branches[] = self::branch(ErrorType::DOMAIN_ERROR, $domainDetails);
        }

        $branches[] = self::branch(ErrorType::INTERNAL_ERROR, self::INTERNAL_ERROR_DETAILS);

        return implode('|', $branches);
    }

    /**
     * @throws ReflectionException
     */
    private static function domainDetails(ServerConfiguration $configuration, Definition $definition): ?string
    {
        $exposedTypes = ExposedExceptions::exposedTypesFor($definition, $configuration);
        if (empty($exposedTypes)) {
            return null;
        }

        return implode('|', array_map(static function (string $exposedType): string {
            $type = json_encode($exposedType, JSON_THROW_ON_ERROR);
            return "{type: {$type}}";
        }, $exposedTypes));
    }

    private static function branch(ErrorType $type, string $details): string
    {
        $name = json_encode($type->name, JSON_THROW_ON_ERROR);
        return "{code: {$type->value}, type: {$name}, details: {$details}}";
    }
}
