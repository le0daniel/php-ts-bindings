<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Errors;

use Le0daniel\PhpTsBindings\Contracts\Attributes\ExposeAs;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Utils\Lists;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Throwable;

/**
 * Which exceptions an operation may surface to the client, and under what name.
 *
 * #[Throws] declares what an operation can throw; #[ExposeAs] on the exception itself decides
 * whether that is something the client is allowed to see. Both the runtime presenter and the
 * TypeScript code generator answer that question here, so a generated error union and the
 * responses it describes can never drift apart.
 */
final readonly class ExposedExceptions
{
    /**
     * Every exception declared via #[Throws], on the operation method and on the handle() method of
     * each of its middlewares. handle() is guaranteed to exist: every middleware implements
     * MiddlewareContract.
     *
     * @param Definition $definition
     * @return list<class-string<Throwable>>
     * @throws ReflectionException
     */
    public static function declaredFor(Definition $definition): array
    {
        $attributes = new ReflectionMethod($definition->fullyQualifiedClassName, $definition->methodName)
            ->getAttributes(Throws::class);

        foreach ($definition->middleware as $middlewareClassName) {
            $middlewareAttributes = new ReflectionMethod($middlewareClassName, 'handle')
                ->getAttributes(Throws::class);

            if (count($middlewareAttributes) > 0) {
                array_push($attributes, ...$middlewareAttributes);
            }
        }

        return array_map(function (ReflectionAttribute $attribute): string {
            /** @var Throws $instance */
            $instance = $attribute->newInstance();
            return $instance->exceptionClass;
        }, $attributes);
    }

    /**
     * @param class-string $exceptionClass
     * @return string|null
     */
    public static function exposedTypeOf(string $exceptionClass): ?string
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
     * @return list<string>
     * @throws ReflectionException
     */
    public static function exposedTypesFor(Definition $definition): array
    {
        return array_map(self::exposedTypeOf(...), self::declaredFor($definition))
            |> Lists::filterNullValues(...)
            |> Lists::unique(...);
    }
}
