<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Presenter;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;
use Le0daniel\PhpTsBindings\Contracts\ClientAwareException;
use Le0daniel\PhpTsBindings\Contracts\ExceptionPresenter;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use ReflectionAttribute;
use ReflectionException;
use ReflectionMethod;
use Throwable;

final class ClientAwareExceptionPresenter implements ExceptionPresenter
{

    /**
     * @param Definition $definition
     * @return list<class-string<ClientAwareException>>
     * @throws ReflectionException
     */
    private function extractExposedExceptions(Definition $definition): array
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
     * @throws ReflectionException
     */
    public function matches(Throwable $throwable, Definition $definition): bool
    {
        return $throwable instanceof ClientAwareException && in_array($throwable::class, $this->extractExposedExceptions($definition), true);
    }

    public function toTypeScriptDefinition(Definition $definition): ?string
    {
        $exceptionClasses = $this->extractExposedExceptions($definition);
        if (empty($exceptionClasses)) {
            return null;
        }

        return implode('|', array_map(function (string $exceptionClass): string {
            $type = json_encode($exceptionClass::type(), JSON_THROW_ON_ERROR);
            return "{type: {$type}}";
        }, $exceptionClasses));
    }

    /**
     * @return array{type: string}
     */
    public function details(Throwable $throwable): array
    {
        /** @var ClientAwareException $throwable */
        return [
            'type' => $throwable::type(),
        ];
    }

    public static function errorType(): ErrorType
    {
        return ErrorType::DOMAIN_ERROR;
    }
}