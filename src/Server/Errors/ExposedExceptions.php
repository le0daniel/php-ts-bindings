<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Errors;

use Le0daniel\PhpTsBindings\Contracts\Attributes\ExposeAs;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Utils\Lists;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Throwable;

/**
 * Which exceptions an operation may surface to the client, and under what name.
 *
 * #[Throws] declares what an operation can throw and may name it right there via `as`; #[ExposeAs]
 * on the exception itself is the name to fall back on. An exception with neither is declared but
 * unnamed, and stays internal. Both the runtime presenter and the TypeScript code generator answer
 * that question here, so a generated error union and the responses it describes can never drift
 * apart.
 */
final readonly class ExposedExceptions
{
    /**
     * Every exception declared via #[Throws], on the operation method and on the handle() method of
     * each of its middlewares, mapped to the name the client sees - or null where it stays
     * internal. handle() is guaranteed to exist: every middleware implements MiddlewareContract.
     *
     * Both the middleware the operation declares with #[Middleware] and the middleware the server
     * is configured with are read, because both wrap the operation and either can be the thing
     * that throws. They are reflected in that order so an operation's own declaration keeps
     * winning over a server wide one.
     *
     * @param Definition $definition
     * @param ServerConfiguration $configuration
     * @return array<class-string<Throwable>, string|null>
     * @throws ReflectionException
     */
    public static function declaredFor(Definition $definition, ServerConfiguration $configuration): array
    {
        $attributes = new ReflectionMethod($definition->fullyQualifiedClassName, $definition->methodName)
            ->getAttributes(Throws::class);

        $middlewareClassNames = [
            ... $definition->middleware,
            ... $configuration->middleware,
        ];

        foreach ($middlewareClassNames as $middlewareClassName) {
            $middlewareAttributes = new ReflectionMethod($middlewareClassName, 'handle')
                ->getAttributes(Throws::class);

            if (count($middlewareAttributes) > 0) {
                array_push($attributes, ...$middlewareAttributes);
            }
        }

        $declared = [];
        foreach ($attributes as $attribute) {
            /** @var Throws $throws */
            $throws = $attribute->newInstance();

            // The first name given wins. The operation is reflected before the middleware wrapping
            // it, so an operation states its own contract first; and because only a name displaces
            // null, a bare #[Throws] never silences an `as` declared elsewhere for the same class.
            $declared[$throws->exceptionClass] ??= $throws->as ?? self::exposeAsOf($throws->exceptionClass);
        }

        return $declared;
    }

    /**
     * The name an exception class gives itself, used when no #[Throws] names it.
     *
     * @param class-string $exceptionClass
     */
    private static function exposeAsOf(string $exceptionClass): ?string
    {
        $attributes = new ReflectionClass($exceptionClass)->getAttributes(ExposeAs::class);
        return count($attributes) === 0
            ? null
            : $attributes[0]->newInstance()->type;
    }

    /**
     * The exposed names of every exception the operation declares, in declaration order.
     *
     * @param Definition $definition
     * @param ServerConfiguration $configuration
     * @return list<string>
     * @throws ReflectionException
     */
    public static function exposedTypesFor(Definition $definition, ServerConfiguration $configuration): array
    {
        return array_values(self::declaredFor($definition, $configuration))
            |> Lists::filterNullValues(...)
            |> Lists::unique(...);
    }
}
