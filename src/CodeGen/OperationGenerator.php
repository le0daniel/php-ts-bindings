<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen;

use Le0daniel\PhpTsBindings\Adapters\Laravel\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Operations\Data\Operation;
use Le0daniel\PhpTsBindings\CodeGen\Data\DefinitionTarget;
use Le0daniel\PhpTsBindings\Utils\Arrays;

final readonly class OperationGenerator
{
    public function __construct(
        private TypescriptDefinitionGenerator $definitionGenerator = new TypescriptDefinitionGenerator()
    )
    {
    }

    /**
     * @param OperationRegistry $registry
     * @return array<string, string>
     */
    public function generate(OperationRegistry $registry): array
    {
        /** @var array<string, list<Operation>> $groupedByNamespace */
        $groupedByNamespace = [];
        foreach ($registry->all() as $endpoint) {
            $groupedByNamespace[$endpoint->definition->namespace] ??= [];
            $groupedByNamespace[$endpoint->definition->namespace][] = $endpoint;
        }

        return Arrays::mapWithKeys($groupedByNamespace, function (string $namespace, array $endpoints): string {
            return implode(
                PHP_EOL,
                array_map(fn(Operation $endpoint) => match ($endpoint->definition->type) {
                    'query' => $this->generateQueryEndpoint($endpoint),
                    'command' => $this->generateCommandEndpoint($endpoint),
                }, $endpoints)
            );
        });
    }

    private function generateQueryEndpoint(Operation $endpoint): string
    {
        $inputDefinition = $this->definitionGenerator->toDefinition($endpoint->inputNode(), DefinitionTarget::INPUT);
        $outputDefinition = $this->definitionGenerator->toDefinition($endpoint->outputNode(), DefinitionTarget::OUTPUT);

        return implode(PHP_EOL, [
            "/**",
            " * Query",
            " * {$endpoint->definition->description}",
            " * @php {$endpoint->definition->fullyQualifiedClassName}::{$endpoint->definition->methodName}",
            " */",
            <<<TS
export async function {$endpoint->definition->name}(input: {$inputDefinition}, options?: QueryOptions): Promise<{$outputDefinition}> {
    return await executeQuery<{$outputDefinition}>(input, options);   
}
{$endpoint->definition->name}.key = (input: {$inputDefinition}) => ['{$endpoint->definition->namespace}', '{$endpoint->definition->name}', input];
TS,
        ]);
    }

    private function generateCommandEndpoint(Operation $endpoint): string {
        $inputDefinition = $this->definitionGenerator->toDefinition($endpoint->inputNode(), DefinitionTarget::INPUT);
        $outputDefinition = $this->definitionGenerator->toDefinition($endpoint->outputNode(), DefinitionTarget::OUTPUT);

        return implode(PHP_EOL, [
            "/**",
            " * Command",
            " * {$endpoint->definition->description}",
            " * @php {$endpoint->definition->fullyQualifiedClassName}::{$endpoint->definition->methodName}",
            " */",
            <<<TS
export async function {$endpoint->definition->name}(input: {$inputDefinition}, options?: QueryOptions): Promise<{$outputDefinition}> {
    return await executeQuery<{$outputDefinition}>(input, options);   
}
TS,
        ]);
    }
}