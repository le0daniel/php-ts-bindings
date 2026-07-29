<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\Contracts\DependsOn;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesOperationCode;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\CodeGen\Exceptions\InvalidGeneratorDependencies;
use Le0daniel\PhpTsBindings\Contracts\ExceptionPresenter;
use Le0daniel\PhpTsBindings\Parser\AstValidator;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Server;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Data\IO;
use Le0daniel\PhpTsBindings\Typescript\Data\TypeScript;
use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;
use Le0daniel\PhpTsBindings\Typescript\TypescriptGenerator;
use Le0daniel\PhpTsBindings\Utils\Lists;
use RuntimeException;

final readonly class TypescriptServerCodeGenerator
{
    /**
     * @param array<GeneratesLibFiles|GeneratesOperationCode> $generators
     * @throws InvalidGeneratorDependencies
     */
    public function __construct(
        private array               $generators,
        private TypescriptGenerator $typescriptGenerator = new TypescriptGenerator(),
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
     * @return array<string, TypescriptFile>
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

        // Cross-operation and cross-direction alias conflicts are only caught when every pass hands
        // its aliases into one shared registry, so the run always has one. It is also what the
        // generated types file declares.
        $registry = new AliasRegistry();

        $definitions = array_values(
            array_map(function (Operation $operation) use ($server, $registry): TypedOperation {
                AstValidator::validate($operation->inputNode());
                AstValidator::validate($operation->outputNode());

                $input = $this->typescriptGenerator->toTypescript(
                    $operation->inputNode(), IO::INPUT, $registry,
                );
                $output = $this->typescriptGenerator->toTypescript(
                    $operation->outputNode(), IO::OUTPUT, $registry,
                );

                return new TypedOperation(
                    $input,
                    $output,
                    TypeScript::fromRawString($this->generateAllErrorTypes($server, $operation->definition)),
                    $operation,
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
            ...$this->generateLibFiles($definitions, $metadata, $registry),
            ...$this->generateOperationDefinitions($definitions, $metadata),
        ];
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
     * @param AliasRegistry $registry The run's shared registry, holding every alias any pass produced.
     * @return array<string, TypescriptFile>
     */
    private function generateLibFiles(array $definitions, ServerMetadata $metadata, AliasRegistry $registry): array
    {
        return array_reduce(
            $this->generators,
            /**
             * @param array<string, TypescriptFile> $carry
             * @return array<string, TypescriptFile>
             */
            function (array $carry, $codeGenerator) use ($definitions, $metadata, $registry): array {
                if (!$codeGenerator instanceof GeneratesLibFiles) {
                    return $carry;
                }

                foreach ($codeGenerator->emitFiles($definitions, $metadata, $registry) as $fileName => $fileContent) {
                    if (preg_match('/^[a-zA-Z0-9_\-]+$/', $fileName) !== 1) {
                        throw new RuntimeException("Invalid file name '{$fileName}' for lib file. File names must only contain a-z, A-Z, 0-9, - and _.");
                    }

                    // Several generators may contribute to one lib file, so they accumulate rather
                    // than overwrite.
                    $fileKey = "lib/{$fileName}.ts";
                    $carry[$fileKey] = ($carry[$fileKey] ?? new TypescriptFile())->append($fileContent);
                }
                return $carry;
            },
            []
        );
    }

    /**
     * @param list<TypedOperation> $definitions
     * @param ServerMetadata $metadata
     * @return array<string, TypescriptFile>
     */
    private function generateOperationDefinitions(array $definitions, ServerMetadata $metadata): array
    {
        /** @var array<string, TypescriptFile> $operationFiles */
        $operationFiles = [];
        foreach ($definitions as $operationData) {
            $fileKey = "{$operationData->definition->namespace}.ts";

            // The file is immutable, so each block produces a new one and the last is kept. It also
            // owns the blank lines between blocks, which is why nothing is appended as a separator.
            $file = $operationFiles[$fileKey] ?? new TypescriptFile();

            foreach ($this->generators as $codeGenerator) {
                if (!$codeGenerator instanceof GeneratesOperationCode) {
                    continue;
                }

                if ($code = $codeGenerator->generateOperationCode($operationData, $metadata)) {
                    $file = $file->append($code);
                }
            }

            $operationFiles[$fileKey] = $file;
        }

        return $operationFiles;
    }
}