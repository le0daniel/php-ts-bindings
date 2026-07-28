<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Presenter;

use Le0daniel\PhpTsBindings\Contracts\Attributes\ExposeAs;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;
use Le0daniel\PhpTsBindings\Contracts\ExceptionPresenter;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Throwable;

final class ExposedExceptionPresenter implements ExceptionPresenter
{

    /**
     * @param Definition $definition
     * @return list<class-string<Throwable>>
     * @throws ReflectionException
     */
    private function extractDeclaredExceptions(Definition $definition): array
    {
        $reflection = new ReflectionMethod($definition->fullyQualifiedClassName, $definition->methodName);
        $attributes = $reflection->getAttributes(Throws::class);

        // We go through all middleware and extract their throws attributes
        if (count($definition->middleware) > 0) {
            foreach ($definition->middleware as $middlewareClassName) {
                $reflection = new ReflectionMethod($middlewareClassName, 'handle');
                $middlewareAttributes = $reflection->getAttributes(Throws::class);
                if (count($middlewareAttributes) > 0) {
                    array_push($attributes, ...$middlewareAttributes);
                }
            }
        }

        return array_map(function (ReflectionAttribute $attribute) {
            /** @var Throws $instance */
            $instance = $attribute->newInstance();
            return $instance->exceptionClass;
        }, $attributes);
    }

    /**
     * @param class-string $exceptionClass
     */
    private function exposedTypeOf(string $exceptionClass): ?string
    {
        $attributes = new ReflectionClass($exceptionClass)->getAttributes(ExposeAs::class);
        if (count($attributes) === 0) {
            return null;
        }

        return $attributes[0]->newInstance()->type;
    }

    /**
     * @throws ReflectionException
     */
    public function matches(Throwable $throwable, Definition $definition): bool
    {
        return $this->exposedTypeOf($throwable::class) !== null
            && in_array($throwable::class, $this->extractDeclaredExceptions($definition), true);
    }

    public function toTypeScriptDefinition(Definition $definition): ?string
    {
        $exposedTypes = array_filter(array_map(
            $this->exposedTypeOf(...),
            $this->extractDeclaredExceptions($definition),
        ));

        if (empty($exposedTypes)) {
            return null;
        }

        return implode('|', array_map(function (string $exposedType): string {
            $type = json_encode($exposedType, JSON_THROW_ON_ERROR);
            return "{type: {$type}}";
        }, $exposedTypes));
    }

    /**
     * @return array{type: string}
     */
    public function details(Throwable $throwable): array
    {
        return [
            'type' => $this->exposedTypeOf($throwable::class),
        ];
    }

    public static function errorType(): ErrorType
    {
        return ErrorType::DOMAIN_ERROR;
    }
}
