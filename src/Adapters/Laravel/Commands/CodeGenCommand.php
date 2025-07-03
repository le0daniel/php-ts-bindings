<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use JsonException;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelHttpController;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Utils\OperationDescription;
use Le0daniel\PhpTsBindings\CodeGen\Data\DefinitionTarget;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptDefinitionGenerator;
use Le0daniel\PhpTsBindings\Contracts\ClientAwareException;
use Le0daniel\PhpTsBindings\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Operations\Data\Operation;
use Le0daniel\PhpTsBindings\Operations\JustInTimeDiscoveryRegistry;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class CodeGenCommand extends Command
{
    private TypescriptDefinitionGenerator $typescriptGenerator;
    protected $signature = 'operations:codegen {directory}';
    protected $description = 'Generate the typescript bindings for all operations';

    /**
     * @throws \ReflectionException
     * @throws JsonException
     */
    public function handle(OperationRegistry $registry, Router $router): void
    {
        $this->typescriptGenerator = new TypescriptDefinitionGenerator();
        if (!$registry instanceof JustInTimeDiscoveryRegistry) {
            throw new RuntimeException('Cannot generate code for a registry that is not a JustInTimeDiscoveryRegistry');
        }

        $directory = str_starts_with('/', $this->argument('directory'))
            ? $this->argument('directory')
            : base_path($this->argument('directory'));

        $this->clearDirectory($directory);

        $namespaces = array_keys($registry->getAllByNamespace());

        $this->generateLib($directory, $registry);
        $this->generateOperationUtility($directory, $router);

        foreach ($registry->getAllByNamespace() as $namespace => $operations) {
            file_put_contents(
                "{$directory}/{$namespace}.ts",
                implode(PHP_EOL, [
                    $this->generateOperationImports(),
                    '',
                    ... array_map($this->generateOperationCode(...), $operations)
                ]) . PHP_EOL
            );
        }
    }

    private function generateOperationImports(): string
    {
        return <<<TypeScript
import type { OperationOptions, Result } from './lib/types';
import { executeOperation, throwOnFailure } from './lib/bindings';
import { queryKey } from './lib/utils';
import { useQuery } from '@tanstack/react-query';

TypeScript;
    }

    /**
     * @throws JsonException
     */
    private function generateLib(string $directory, OperationRegistry $registry): void
    {
        if (!is_dir("{$directory}/lib")) {
            mkdir("{$directory}/lib");
        }

        $namespaces = array_reduce($registry->all(), function (array $carry, Operation $operation) {
            if (!in_array($operation->definition->namespace, $carry, true)) {
                $carry[] = $operation->definition->namespace;
            }
            return $carry;
        }, []);
        $namespaceUnion = implode('|', array_map(fn(string $namespace) => json_encode($namespace, flags: JSON_THROW_ON_ERROR), $namespaces));

        $invalidations = array_reduce($registry->all(), function (array $carry, Operation $operation) {
            if ($operation->definition->type !== 'query') {
                return $carry;
            }

            $carry[$operation->definition->namespace] ??= [];
            $carry[$operation->definition->namespace][] = $operation->definition->name;
            return $carry;
        }, []);

        $invalidationMap = Arrays::mapWithKeys($invalidations, function (string $namespace, array $operations) {
            return "{$namespace}: " . implode('|', array_map(fn(string $operation) => json_encode($operation, flags: JSON_THROW_ON_ERROR), $operations));
        });

        file_put_contents("{$directory}/lib/types.ts", $this->getTemplate('types', [
            "'NAMESPACE_UNION'" => $namespaceUnion,
            "{namespace: 'one'|'two'}" => "{" . implode(';', $invalidationMap) . "}",
        ]));
        file_put_contents("{$directory}/lib/OperationClient.ts", $this->getTemplate('OperationClient'));
        file_put_contents("{$directory}/lib/DefaultClient.ts", $this->getTemplate('DefaultClient'));
        file_put_contents("{$directory}/lib/utils.ts", $this->getTemplate('utils'));
    }

    /**
     * @param string $name
     * @param array<string, string> $params
     * @return string
     */
    private function getTemplate(string $name, array $params = []): string
    {
        $content = file_get_contents(__DIR__ . "/templates/{$name}.ts");
        return str_replace(array_keys($params), array_values($params), $content);
    }

    private function generateOperationUtility(string $directory, Router $router): void
    {
        $queryRoute = str_replace('/{fqn}', '', $router->getRoutes()->getByName(LaravelHttpController::QUERY_NAME)->uri());
        $commandRoute = str_replace('/{fqn}', '', $router->getRoutes()->getByName(LaravelHttpController::COMMAND_NAME)->uri());

        file_put_contents("{$directory}/lib/bindings.ts", $this->getTemplate('bindings', [
            '{queryRoute}' => $queryRoute,
            '{commandRoute}' => $commandRoute,
        ]));
    }

    private function generateErrorTypes(Operation $operation): ?string
    {
        if (empty($operation->definition->caughtExceptions)) {
            return null;
        }

        return implode('|', array_map(
            function (string $clientAwareError): string {
                /** @var class-string<ClientAwareException> $clientAwareError */
                $code = $clientAwareError::code();
                $type = $clientAwareError::type();
                return "{code:{$code};type:'{$type}';}";
            },
            $operation->definition->caughtExceptions,
        ));
    }

    private function generateOperationCode(Operation $operation): string
    {
        $inputDefinition = $this->typescriptGenerator->toDefinition($operation->inputNode(), DefinitionTarget::INPUT);
        $outputDefinition = $this->typescriptGenerator->toDefinition($operation->outputNode(), DefinitionTarget::OUTPUT);
        $handledErrors = $this->generateErrorTypes($operation);
        $handledErrorsOrNever = $handledErrors ? "{$handledErrors}" : 'never';
        $resultType = $handledErrors ? "Result<{$outputDefinition},{$handledErrors}>" : "Result<{$outputDefinition}>";

        $description = OperationDescription::describe($operation);

        return <<<TypeScript
{$description}
export async function {$operation->definition->name}(input: {$inputDefinition}, options?: OperationOptions): Promise<{$resultType}> {
    return await executeOperation<{$inputDefinition},{$outputDefinition}, {$handledErrorsOrNever}>('{$operation->definition->type}', '{$operation->definition->fullyQualifiedName()}', input, options);
}
{$this->generateQueryFunctionAndKey($operation)}

TypeScript;
    }

    /**
     * @throws JsonException
     */
    private function generateQueryFunctionAndKey(Operation $operation): string
    {
        if ($operation->definition->type !== 'query') {
            return '';
        }

        $queryFnName = ucfirst($operation->definition->name);
        $namespace = json_encode($operation->definition->namespace, JSON_THROW_ON_ERROR);
        $name = json_encode($operation->definition->name, JSON_THROW_ON_ERROR);
        $inputDefinition = $this->typescriptGenerator->toDefinition($operation->inputNode(), DefinitionTarget::INPUT);
        $outputDefinition = $this->typescriptGenerator->toDefinition($operation->outputNode(), DefinitionTarget::OUTPUT);

        return <<<TypeScript
/**
 * This is a react query function for a query operation. Defined in:
 * @php {$operation->definition->fullyQualifiedClassName}::{$operation->definition->methodName}
 */
export function use{$queryFnName}Query(input: {$inputDefinition}, queryOptions?: Partial<{enabled: boolean}>) {
    return useQuery({
        queryKey: queryKey('{$operation->definition->fullyQualifiedName()}', input),
        queryFn: async ({signal}): Promise<{$outputDefinition}> => {
            const result = await {$operation->definition->name}(input, {signal});
            throwOnFailure(result);
            return result.data;
        },
        ... queryOptions,
    });
}
TypeScript;
    }

    private function clearDirectory(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isDir() || !str_ends_with($file->getBasename(), '.ts')) {
                continue;
            }

            unlink($file->getRealPath());
        }
    }
}