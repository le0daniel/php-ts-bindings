<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Errors;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Utils\Lists;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

final readonly class ThrowAttributeResolver
{
    /**
     * @param Definition $definition
     * @return list<string>
     * @throws ReflectionException
     */
    public static function collectDomainErrorNamesFromDefinition(
        Definition $definition,
    ): array
    {
        $reflections = [
            new ReflectionMethod($definition->fullyQualifiedClassName, $definition->methodName),
            ... array_map(static fn($className) => new ReflectionMethod($className, 'handle'), $definition->middleware),
        ];

        $names = [];
        foreach ($reflections as $reflection) {
            $data = self::resolveReflection($reflection, allowDomainErrors: true)['data'];
            foreach ($data as $exceptionDeclaration) {
                if (isset($exceptionDeclaration['name'])) {
                    $names[] = $exceptionDeclaration['name'];
                }
            }
        }

        return $names |> Lists::unique(...);
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod $reflection
     * @param bool $allowDomainErrors
     * @return array{data: array<class-string, array{type: ErrorType, name?: string}>, issues: list<string>}
     */
    public static function resolveReflection(
        ReflectionClass|ReflectionMethod $reflection,
        bool $allowDomainErrors,
    ): array
    {
        $issues = [];
        $exceptions = [];

        $throwing = self::throwableAttributes($reflection);

        foreach ($throwing as $throws) {
            if (array_key_exists($throws->exceptionClass, $exceptions)) {
                $issues[] = "Exception ({$throws->exceptionClass}) is already declared.";
                continue;
            }

            if (!$throws->isValid()) {
                $issues[] = "#[Throw] attribute declaration is not valid.";
                continue;
            }

            // Retrieve the correct declaration
            $declaration = $throws->requiresThrowableReflection()
                ? $throws->getExposedAsOrNullThroughReflection()
                : $throws;

            if (!$declaration) {
                $issues[] = "#[ExposeAs] not present on thrown class: {$throws->exceptionClass}.";
                continue;
            }

            if (!$declaration->isValid()) {
                $attributeName = $declaration::class
                        |> (static fn($name) => explode('\\', $name))
                        |> array_last(...);

                $issues[] = "#[{$attributeName}] attribute declaration is not valid.";
                continue;
            }

            // Always ignore InvalidInput
            if ($declaration->type === null || $declaration->type === ErrorType::INVALID_INPUT) {
                $issues[] = "A declaration of type '{$declaration->type?->name}' is not valid.";
                continue;
            }

            // Always ignore DomainError if not allowed
            if ($declaration->type === ErrorType::DOMAIN_ERROR && !$allowDomainErrors) {
                $issues[] = "Domain errors not allowed in this scope.";
                continue;
            }

            $definition = ['type' => $declaration->type];
            if ($declaration->name) {
                $definition['name'] = $declaration->name;
            }
            $exceptions[$throws->exceptionClass] = $definition;
        }

        return [
            "data" => $exceptions,
            "issues" => $issues,
        ];
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod $reflection
     * @return list<Throws>
     */
    private static function throwableAttributes(
        ReflectionClass|ReflectionMethod $reflection,
    ): array
    {
        $attributes = $reflection->getAttributes(Throws::class);
        return array_map(
            static fn(ReflectionAttribute $attribute): Throws => $attribute->newInstance(),
            $attributes,
        );
    }
}