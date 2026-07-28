<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\Contracts\DependsOn;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesOperationCode;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\CodeGen\Exceptions\InvalidGeneratorDependencies;
use Le0daniel\PhpTsBindings\CodeGen\Helpers\TypeScriptFile;
use Le0daniel\PhpTsBindings\Contracts\ExceptionPresenter;
use Le0daniel\PhpTsBindings\Parser\AstValidator;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Server;
use Le0daniel\PhpTsBindings\Typescript\Data\IO;
use Le0daniel\PhpTsBindings\Typescript\Data\Options;
use Le0daniel\PhpTsBindings\Typescript\Data\TypeRegistry;
use Le0daniel\PhpTsBindings\Typescript\TypescriptGenerator;
use Le0daniel\PhpTsBindings\Utils\Lists;
use RuntimeException;

final readonly class TypescriptServerCodeGenerator
{
    /**
     * @param array<GeneratesLibFiles|GeneratesOperationCode> $generators
     * @param Options $options Applies to every type generated in this run. The registry it carries is
     *        ignored: each operation collects its aliases into its own.
     * @throws InvalidGeneratorDependencies
     */
    public function __construct(
        private array               $generators,
        private TypescriptGenerator $typescriptGenerator = new TypescriptGenerator(),
        private Options             $options = new Options(),
    )
    {
        $this->verifyGeneratorDependencies();
    }

    /**
     * @throws InvalidGeneratorDependencies
     */
    private function verifyGeneratorDependencies(): void
    {
        $issues = [];
        $generatorClassNames = array_map(fn(object $generator): string => $generator::class, $this->generators);

        foreach ($this->generators as $generator) {
            if (!$generator instanceof DependsOn) {
                continue;
            }

            foreach ($generator->dependsOnGenerator() as $className) {
                if (!in_array($className, $generatorClassNames, true)) {
                    $issues[] = "Generator " . $generator::class . " depends on {$className} which is not registered.";
                }
            }
        }

        if (!empty($issues)) {
            throw new InvalidGeneratorDependencies($issues);
        }
    }

    /**
     * @param Server $server
     * @param ServerMetadata $metadata
     * @param list<string> $ignore
     * @return array<string, TypeScriptFile>
     */
    public function generate(Server $server, ServerMetadata $metadata, array $ignore = []): array
    {
        /**
         * Filter out some operations that are not needed.
         * @var array<int|string, Operation> $filteredDefinitions
         */
        $filteredDefinitions = array_values(
            array_filter($server->registry->all(), function (Operation $operation) use ($ignore): bool {
                if (in_array($operation->definition->namespace, $ignore, true) || in_array($operation->definition->fullyQualifiedName(), $ignore, true)) {
                    return false;
                }
                return true;
            })
        );

        $definitions = array_values(
            array_map(function (Operation $operation) use ($server): TypedOperation {
                AstValidator::validate($operation->inputNode());
                AstValidator::validate($operation->outputNode());

                // Both directions share one registry, so an operation hands its aliases on as a single
                // set and a brand that means two different things across them is rejected right here.
                $input = $this->typescriptGenerator->toTypescript(
                    $operation->inputNode(), IO::INPUT, $this->optionsWith(new TypeRegistry()),
                );
                $output = $this->typescriptGenerator->toTypescript(
                    $operation->outputNode(), IO::OUTPUT, $this->optionsWith($input->registry),
                );

                return new TypedOperation(
                    $input->type,
                    $output->type,
                    $this->generateAllErrorTypes($server, $operation->definition),
                    $operation,
                    $output->registry,
                );
            }, $filteredDefinitions)
        );

        // Deterministically sort for consistency between systems
        usort($definitions, function (TypedOperation $a, TypedOperation $b): int {
            return strcmp(
                "{$a->definition->fullyQualifiedName()}#{$a->definition->type->name}",
                "{$b->definition->fullyQualifiedName()}#{$b->definition->type->name}",
            );
        });

        return [
            ...$this->generateLibFiles($definitions, $metadata),
            ...$this->generateOperationDefinitions($definitions, $metadata),
        ];
    }

    /**
     * The run wide options, aimed at the given registry.
     */
    private function optionsWith(TypeRegistry $registry): Options
    {
        return new Options(
            pretty: $this->options->pretty,
            ignoreBrandedTypes: $this->options->ignoreBrandedTypes,
            registry: $registry,
        );
    }

    private function generateAllErrorTypes(Server $server, Definition $operation): string
    {
        $possibleTypes = Lists::filterNullValues(array_map(function (ExceptionPresenter $presenter) use ($operation): null|string {
            $code = $presenter::errorType();
            $details = $presenter->toTypeScriptDefinition($operation);
            return $details === null ? null : "{code: {$code->value}, details: {$details}}";
        }, [...$server->exceptionPresenters, $server->defaultPresenter]));

        return implode('|', $possibleTypes);
    }

    /**
     * @param list<TypedOperation> $definitions
     * @param ServerMetadata $metadata
     * @return array<string, TypeScriptFile>
     */
    private function generateLibFiles(array $definitions, ServerMetadata $metadata): array
    {
        return array_reduce(
            $this->generators,
            function (array $carry, $codeGenerator) use ($definitions, $metadata): array {
                if (!$codeGenerator instanceof GeneratesLibFiles) {
                    return $carry;
                }

                foreach ($codeGenerator->emitFiles($definitions, $metadata) as $fileName => $fileContent) {
                    if (preg_match('/^[a-zA-Z0-9_\-]+$/', $fileName) !== 1) {
                        throw new RuntimeException("Invalid file name '{$fileName}' for lib file. File names must only contain a-z, A-Z, 0-9, - and _.");
                    }

                    $carry["lib/{$fileName}.ts"] ??= new TypeScriptFile();
                    $carry["lib/{$fileName}.ts"]->merge(TypeScriptFile::from($fileContent));
                }
                return $carry;
            },
            []
        );
    }

    /**
     * @param list<TypedOperation> $definitions
     * @param ServerMetadata $metadata
     * @return array<string, TypeScriptFile>
     */
    private function generateOperationDefinitions(array $definitions, ServerMetadata $metadata): array
    {
        /** @var array<string, TypeScriptFile> $operationFiles */
        $operationFiles = [];
        foreach ($definitions as $operationData) {
            $namespace = $operationData->definition->namespace;
            $file = $operationFiles["{$namespace}.ts"] ??= new TypeScriptFile();

            foreach ($this->generators as $codeGenerator) {
                if (!$codeGenerator instanceof GeneratesOperationCode) {
                    continue;
                }

                if ($code = $codeGenerator->generateOperationCode($operationData, $metadata)) {
                    $file->append($code);
                }
            }

            $file->append(PHP_EOL . PHP_EOL);
        }

        return $operationFiles;
    }
}