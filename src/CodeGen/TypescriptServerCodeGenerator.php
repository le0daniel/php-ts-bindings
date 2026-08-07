<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperations;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\DependsOn;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesOperationCode;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\CodeGen\Exceptions\CodeGenException;
use Le0daniel\PhpTsBindings\CodeGen\Exceptions\InvalidGeneratorDependencies;
use Le0daniel\PhpTsBindings\CodeGen\Utils\ErrorTypescript;
use Le0daniel\PhpTsBindings\CodeGen\Utils\Paths;
use Le0daniel\PhpTsBindings\Data\IO;
use Le0daniel\PhpTsBindings\Parser\Helpers\AstValidator;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Server;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;
use Le0daniel\PhpTsBindings\Typescript\TypescriptGenerator;

final readonly class TypescriptServerCodeGenerator
{
    /**
     * Every name that becomes a file on disk, whether a lib file a generator named or a module a
     * namespace named, is held to this.
     */
    private const string VALID_MODULE_NAME = '/^[a-zA-Z0-9_\-]+$/';

    /**
     * @param array<GeneratesLibFiles|GeneratesOperationCode> $generators
     *
     * @throws InvalidGeneratorDependencies
     */
    public function __construct(
        private array               $generators,
        private TypescriptGenerator $typescriptGenerator = new TypescriptGenerator(),
    ) {
        $this->resolveGeneratorDependencies();
    }

    /**
     * Every declared dependency is verified before any instance is handed out: a generator asked to
     * resolve a dependency that is not registered would fail on the missing instance instead of the
     * message naming what to register.
     *
     * @throws InvalidGeneratorDependencies
     */
    private function resolveGeneratorDependencies(): void
    {
        $issues = [];

        /** @var array<class-string<GeneratesLibFiles|GeneratesOperationCode>, GeneratesLibFiles|GeneratesOperationCode> $instances */
        $instances = [];
        foreach ($this->generators as $generator) {
            $instances[$generator::class] = $generator;
        }

        foreach ($this->generators as $generator) {
            if (!$generator instanceof DependsOn) {
                continue;
            }

            foreach ($generator->dependsOnGenerator() as $className) {
                if (!array_key_exists($className, $instances)) {
                    $issues[] = 'Generator ' . $generator::class . " depends on {$className} which is not registered.";
                }
            }
        }

        if (count($issues) > 0) {
            throw new InvalidGeneratorDependencies($issues);
        }

        foreach ($this->generators as $generator) {
            if (!$generator instanceof DependsOn) {
                continue;
            }

            // Each generator sees what it declared and nothing else, so a dependency it never asked
            // for cannot quietly become one it relies on.
            array_flip($generator->dependsOnGenerator())
                |> (static fn ($x) => array_intersect_key($instances, $x))
                |> $generator->setDependencies(...);
        }
    }

    /**
     * @param list<string> $ignore
     * @return array<string, TypescriptFile>
     */
    public function generate(Server $server, ServerMetadata $metadata, array $ignore = []): array
    {
        /**
         * Filter out some operations that are not needed.
         *
         * @var array<int|string, Operation> $filteredDefinitions
         */
        $filteredDefinitions = array_filter(
            $server->registry->all(),
            fn (Operation $operation): bool => !in_array($operation->definition->namespace, $ignore, true)
                    && !in_array($operation->definition->fullyQualifiedName(), $ignore, true),
        ) |> array_values(...);

        // Cross-operation and cross-direction alias conflicts are only caught when every pass hands
        // its aliases into one shared registry, so the run always has one. It is also what the
        // generated types file declares.
        $registry = new AliasRegistry();

        // Bound once: inputNode()/outputNode() run the parse closure on every call, so asking twice
        // parses every schema in the run twice.
        $definitions = array_map(function (Operation $operation) use ($server, $registry): TypedOperation {
            $inputNode = $operation->inputNode();
            $outputNode = $operation->outputNode();

            AstValidator::validate($inputNode);
            AstValidator::validate($outputNode);

            return new TypedOperation(
                inputDef: $this->typescriptGenerator->toTypescript($inputNode, IO::INPUT, $registry),
                outputDef: $this->typescriptGenerator->toTypescript($outputNode, IO::OUTPUT, $registry),
                domainErrors: ErrorTypescript::domainTypesFor($server->configuration, $operation->definition),
                operation: $operation,
            );
        }, $filteredDefinitions);

        // Deterministically sort for consistency between systems
        usort($definitions, function (TypedOperation $a, TypedOperation $b): int {
            return strcmp(
                "{$a->definition->fullyQualifiedName()}#{$a->definition->type->name}",
                "{$b->definition->fullyQualifiedName()}#{$b->definition->type->name}",
            );
        });

        // Asked of the generator that owns the naming rule, so a custom --naming that already
        // distinguishes the two is not rejected for a clash it does not produce. After the sort,
        // so which of a clashing pair is named first does not depend on discovery order.
        foreach ($this->generators as $codeGenerator) {
            if ($codeGenerator instanceof EmitOperations) {
                $codeGenerator->assertNamesAreUnique($definitions);
            }
        }

        return [
            ...$this->generateLibFiles($definitions, $metadata, $registry),
            ...$this->generateOperationDefinitions($definitions, $metadata),
        ];
    }

    /**
     * @param list<TypedOperation> $definitions
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
                    if (preg_match(self::VALID_MODULE_NAME, $fileName) !== 1) {
                        throw new CodeGenException("Invalid file name '{$fileName}' for lib file. File names must only contain a-z, A-Z, 0-9, - and _.");
                    }

                    // Several generators may contribute to one lib file, so they accumulate rather
                    // than overwrite.
                    //
                    // An emitter names a lib file the way a module at the output root reaches it,
                    // because it cannot know where its own output lands. Here it is known: this one
                    // goes into lib/, one directory deeper, where a sibling is reached directly.
                    $fileKey = "lib/{$fileName}.ts";
                    $carry[$fileKey] = ($carry[$fileKey] ?? new TypescriptFile())
                        ->append($fileContent->withModulesResolvedBy(Paths::fromInsideLib(...)));
                }

                return $carry;
            },
            []
        );
    }

    /**
     * @param list<TypedOperation> $definitions
     * @return array<string, TypescriptFile>
     */
    private function generateOperationDefinitions(array $definitions, ServerMetadata $metadata): array
    {
        /** @var array<string, TypescriptFile> $operationFiles */
        $operationFiles = [];
        foreach ($definitions as $operationData) {
            $namespace = $operationData->definition->namespace;

            // Lib file names are validated; this one comes straight from #[Query(namespace: ...)]
            // and is written verbatim. A `/` or `..` in it is path traversal in a build tool, and
            // a quote breaks the namespace literal union EmitTypeUtils emits.
            if (preg_match(self::VALID_MODULE_NAME, $namespace) !== 1) {
                throw new CodeGenException(
                    "Invalid namespace '{$namespace}' on "
                    . "{$operationData->definition->fullyQualifiedClassName}::{$operationData->definition->methodName}. "
                    . 'A namespace becomes a module file name and must only contain a-z, A-Z, 0-9, - and _.'
                );
            }

            $fileKey = "{$namespace}.ts";

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
