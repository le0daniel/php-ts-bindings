<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen;

use Closure;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperationClientBindings;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperations;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperationsSpaClient;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitQueryKey;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTanstackQuery;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypeMap;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypes;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypeUtils;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesOperationCode;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;

/**
 * @phpstan-type GeneratorName 'fqn'|'operation-prefix'|'namespace-postfix'|'name'
 * @phpstan-type NamingGenerator Closure(TypedOperation): string
 */
final readonly class CodeGenerators
{
    /**
     * @var array<string, array{defaultEnabled: bool, class: class-string<GeneratesLibFiles|GeneratesOperationCode>}>
     */
    private const array DEFAULT_GENERATORS = [
        'types' => [
            'defaultEnabled' => true,
            'class' => EmitTypes::class,
        ],
        'bindings' => [
            'defaultEnabled' => true,
            'class' => EmitOperationClientBindings::class,
        ],
        'utils' => [
            'defaultEnabled' => true,
            'class' => EmitTypeUtils::class,
        ],
        'operations-spa' => [
            'defaultEnabled' => true,
            'class' => EmitOperationsSpaClient::class,
        ],
        'operations' => [
            'defaultEnabled' => true,
            'class' => EmitOperations::class,
        ],
        'type-map' => [
            'defaultEnabled' => false,
            'class' => EmitTypeMap::class,
        ],
        'tanstack-query' => [
            'defaultEnabled' => false,
            'class' => EmitTanstackQuery::class,
        ],
        'query-key' => [
            'defaultEnabled' => false,
            'class' => EmitQueryKey::class,
        ],
    ];

    /**
     * @param  GeneratorName  $generatorName
     * @return NamingGenerator
     */
    public static function namingGenerator(string $generatorName): Closure
    {
        return match ($generatorName) {
            'fqn' => function (TypedOperation $operationData): string {
                $namespace = $operationData->definition->namespace;
                $name = ucfirst($operationData->definition->name);

                return "{$namespace}{$name}";
            },
            'operation-prefix' => function (TypedOperation $operationData): string {
                $name = ucfirst($operationData->definition->name);

                return "{$operationData->definition->namespace}{$name}";
            },
            'namespace-postfix' => function (TypedOperation $operationData): string {
                $namespace = ucfirst($operationData->definition->namespace);
                $name = $operationData->definition->name;

                return "{$name}{$namespace}";
            },
            'name' => function (TypedOperation $operationData): string {
                return $operationData->definition->name;
            },
        };
    }

    /**
     * @param  GeneratorName|NamingGenerator  $namingGenerator
     * @param  list<string>  $with
     * @param  list<string>  $without
     * @return list<GeneratesLibFiles|GeneratesOperationCode>
     */
    public static function fromDefaults(string|Closure $namingGenerator, array $with = [], array $without = []): array
    {
        $namingGenerator = $namingGenerator instanceof Closure
            ? $namingGenerator
            : self::namingGenerator($namingGenerator);

        /** @var list<GeneratesLibFiles|GeneratesOperationCode> $generators */
        $generators = [];

        foreach (self::DEFAULT_GENERATORS as $name => ['class' => $classString, 'defaultEnabled' => $defaultEnabled]) {
            // Asking for a generator always wins: a name in both lists turns it on, so a caller
            // building on top of someone else's $without never has to unpick it first.
            $isEnabled = in_array($name, $with, true)
                || ($defaultEnabled && ! in_array($name, $without, true));

            if (! $isEnabled) {
                continue;
            }

            $generators[] = match ($classString) {
                EmitOperations::class => new EmitOperations($namingGenerator),
                default => new $classString(),
            };
        }

        return $generators;
    }
}
