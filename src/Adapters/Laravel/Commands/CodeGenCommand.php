<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands;

use Closure;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Container\Attributes\Give;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators\DependsOn;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators\EmitOperationClientBindings;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators\EmitOperations;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators\EmitQueryKey;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators\EmitTanstackQuery;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators\EmitTypeMap;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators\EmitTypes;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators\EmitTypeUtils;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators\GeneratesOperationCode;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators\TsFile;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\GeneralMetadata;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\OperationData;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelHttpController;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelServiceProvider;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Utils\ArtisanOptions;
use Le0daniel\PhpTsBindings\CodeGen\Data\DefinitionTarget;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptDefinitionGenerator;
use Le0daniel\PhpTsBindings\Contracts\ExceptionPresenter;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Server;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class CodeGenCommand extends Command
{
    protected $signature = 'operations:codegen {directory} '
    . '{--with=* : tanstack-query | type-map} '
    . '{--custom=* : class-string<GeneratesLibFiles | GeneratesOperationCode>} '
    . '{--without=* : tanstack-query | type-map} '
    . '{--ignore=* : Ignored namespaces (namespace) or specific operations by specifying namespace.name} '
    . '{--naming=name : Naming mode to use. Modes: name, fqn, operation-prefix, namespace-postfix or classname::methodName for custom function}'
    . '{--verify} ';

    protected $description = 'Generate the typescript bindings for all operations';

    protected $help = <<<DESCRIPTION
Generate the typescript bindings for all operations
  Use --with=tanstack-query,... or --with=.* --with=.* to include a specific generators like tanstack-query operations.
  
  Following types are available:
    - types (default: true) 
    - bindings (default: true) 
    - utils (default: true) 
    - operations (default: true) 
    - type-map (default: false)
    - tanstack-query (default: false)
    - query-key (default: false)
    
  To provide custom generators, create a class that implements at least one of the following interfaces:
    - GeneratesLibFiles (gets all operations and can write multiple lib files)
    - GeneratesOperationCode (gets each operation as input and writes code for it)
  
  Provide the fully qualified class name in the --custom:
    - --custom=My\Custom\Generator
  
  Ignore usage:
    - Ignore a full namespace: --ignore=namespace
    - Ignore a specific operation: --ignore=namespace.operationName (uses the fully qualified name, not obfuscated)
  
  Use --verify to not emit the files, but just verify that the output is correct of already existing files.
DESCRIPTION;

    public function handle(
        #[Give(LaravelServiceProvider::DEFAULT_SERVER)] Server $server,
        Router                                                 $router,
        Application                                            $application,
        TypescriptDefinitionGenerator                          $typescriptGenerator
    ): int
    {
        $codeGenerators = $this->getGeneratorsFromInput($application);
        if ($codeGenerators === null) {
            $this->error("Could not create generators.");
            return 1;
        }

        $directory = str_starts_with('/', $this->argument('directory'))
            ? $this->argument('directory')
            : base_path($this->argument('directory'));
        $ignored = ArtisanOptions::expandOptionsArrayCommaSeparated($this->option('ignore'));

        /** @var list<OperationData> $definitions */
        $definitions = [];
        foreach ($server->registry->all() as $operation) {
            if (in_array($operation->definition->namespace, $ignored, true) || in_array($operation->definition->fullyQualifiedName(), $ignored, true)) {
                $this->info("- Ignoring operation {$operation->definition->fullyQualifiedName()}");;
                continue;
            }

            $inputType = $typescriptGenerator->toDefinition($operation->inputNode(), DefinitionTarget::INPUT);
            $successOutputType = $typescriptGenerator->toDefinition($operation->outputNode(), DefinitionTarget::OUTPUT);
            $possibleErrorType = $this->generateAllErrorTypes($server, $operation->definition);

            $definitions[] = new OperationData(
                $inputType,
                $successOutputType,
                $possibleErrorType,
                $operation,
            );
        }

        // Deterministically sort for new runs
        usort($definitions, function (OperationData $a, OperationData $b): int {
            return strcmp($a->definition->fullyQualifiedName(), $b->definition->fullyQualifiedName());
        });

        $metadata = new GeneralMetadata(
            $router->getRoutes()->getByName(LaravelHttpController::QUERY_NAME)->uri(),
            $router->getRoutes()->getByName(LaravelHttpController::COMMAND_NAME)->uri(),
        );

        $libFiles = $this->generateLibFiles($codeGenerators, $definitions, $metadata);

        /** @var array<string, TsFile> $operationFiles */
        $operationFiles = [];
        foreach ($definitions as $operationData) {
            $namespace = $operationData->definition->namespace;
            $file = $operationFiles[$namespace] ??= new TsFile();

            foreach ($codeGenerators as $codeGenerator) {
                if (!$codeGenerator instanceof GeneratesOperationCode) {
                    continue;
                }

                $code = $codeGenerator->generateOperationCode($operationData, $metadata);
                if (!$code) {
                    continue;
                }

                if ($code->imports) {
                    $file->addImports(...$code->imports);
                }

                $file->addContent($code->content . PHP_EOL);
            }

            $file->addContent(PHP_EOL . PHP_EOL);
        }

        if ($this->option('verify')) {
            $this->info("Verify generated code only.");
            return $this->verifyContentOnly($directory, $libFiles, $operationFiles);
        }

        $this->writeFiles($directory, $libFiles, $operationFiles);
        return 0;
    }

    /**
     * @return Closure(OperationData): string
     * @throws BindingResolutionException
     */
    private function getNamingGenerator(Application $application): Closure
    {
        $nameGenerator = match ($this->option('naming')) {
            'fqn' => function (OperationData $operationData): string {
                $namespace = $operationData->definition->namespace;
                $name = ucfirst($operationData->definition->name);
                return "{$namespace}{$name}";
            },
            'operation-prefix' => function (OperationData $operationData): string {
                $name = ucfirst($operationData->definition->name);
                return "{$operationData->definition->namespace}{$name}";
            },
            'namespace-postfix' => function (OperationData $operationData): string {
                $namespace = ucfirst($operationData->definition->namespace);
                $name = $operationData->definition->name;
                return "{$name}{$namespace}";
            },
            'name' => function (OperationData $operationData): string {
                return $operationData->definition->name;
            },
            default => null,
        };

        if ($nameGenerator) {
            return $nameGenerator;
        }

        $possibleClassNameAndMethod = $this->option('naming');
        $parts = explode('::', $possibleClassNameAndMethod, 2);

        if (count($parts) === 2 && class_exists($parts[0]) && method_exists($parts[0], $parts[1])) {
            return Closure::fromCallable([$application->make($parts[0]), $parts[1]]);
        }

        $this->error("Unknown naming mode {$this->option('naming')}.");
        exit(1);
    }

    /**
     * @param string $directory
     * @param array<string, TsFile> $libFiles
     * @param array<string, TsFile> $operationFiles
     * @return Generator<string, TsFile, mixed, void>
     */
    private function iterateFiles(string $directory, array $libFiles, array $operationFiles): Generator
    {
        foreach ($libFiles as $fileName => $file) {
            $filePath = "{$directory}/lib/{$fileName}.ts";
            yield $filePath => $file;
        }

        foreach ($operationFiles as $fileName => $file) {
            $filePath = "{$directory}/{$fileName}.ts";
            yield $filePath => $file;
        }
    }

    /**
     * @param string $directory
     * @param array<string, TsFile> $libFiles
     * @param array<string, TsFile> $operationFiles
     * @return int
     */
    private function verifyContentOnly(string $directory, array $libFiles, array $operationFiles): int
    {
        $issues = [];
        foreach ($this->iterateFiles($directory, $libFiles, $operationFiles) as $filePath => $file) {
            if (!file_exists($filePath)) {
                $issues[] = "File {$filePath} does not exist";
                continue;
            }
            if (file_get_contents($filePath) !== $file->toString()) {
                $issues[] = "File {$filePath} does not match";
            }
        }

        if (!empty($issues)) {
            $count = count($issues);

            $this->error("Found {$count} issue(s):");
            foreach ($issues as $issue) {
                $this->info($issue);
            }
            return 1;
        }

        $this->line("All files are correct. No issues found.");
        return 0;
    }

    /**
     * @param string $directory
     * @param array<string, TsFile> $libFiles
     * @param array<string, TsFile> $operationFiles
     * @return void
     */
    private function writeFiles(string $directory, array $libFiles, array $operationFiles): void
    {
        $this->clearDirectory($directory);

        if (!file_exists("{$directory}/lib") && is_dir("{$directory}/lib") === false) {
            mkdir("{$directory}/lib", 0777, true);
        }

        foreach ($this->iterateFiles($directory, $libFiles, $operationFiles) as $filePath => $file) {
            file_put_contents($filePath, $file->toString());
        }
    }

    /**
     * @return list<GeneratesOperationCode|GeneratesLibFiles>|null
     * @throws BindingResolutionException
     */
    private function getGeneratorsFromInput(Application $application): ?array
    {
        $with = ArtisanOptions::expandOptionsArrayCommaSeparated($this->option('with'));
        $without = ArtisanOptions::expandOptionsArrayCommaSeparated($this->option('without'));

        $includeGenerator = function (string $name, bool $default = true) use ($with, $without): bool {
            if (in_array($name, $with, true)) {
                return true;
            }
            if (in_array($name, $without, true)) {
                return false;
            }
            return $default;
        };

        $namingGenerator = $this->getNamingGenerator($application);

        $generators = array_filter([
            $includeGenerator('types', true) ? new EmitTypes() : null,
            $includeGenerator('bindings', true) ? new EmitOperationClientBindings() : null,
            $includeGenerator('utils', true) ? new EmitTypeUtils() : null,
            $includeGenerator('operations', true) ? new EmitOperations($namingGenerator) : null,
            $includeGenerator('type-map', false) ? new EmitTypeMap() : null,
            $includeGenerator('tanstack-query', false) ? new EmitTanstackQuery($namingGenerator) : null,
            $includeGenerator('query-key', false) ? new EmitQueryKey($namingGenerator) : null,
        ], fn($value) => $value !== null);

        $customGenerators = array_map(
            fn(string $className) => $application->make($className),
            ArtisanOptions::expandOptionsArrayCommaSeparated($this->option('custom'))
        );

        // @phpstan-ignore-next-line arrayValues.list
        $allGenerators = array_values([
            ...$generators,
            ...$customGenerators,
        ]);

        $issues = [];
        $classNames = array_map(fn($generator) => $generator::class, $allGenerators);

        foreach ($allGenerators as $generator) {
            if (!$generator instanceof GeneratesLibFiles && !$generator instanceof GeneratesOperationCode) {
                $className = $generator::class;
                $issues[] = "Generator {$className} does not implement one of the interfaces (GeneratesLibFiles or GeneratesOperationCode).";
                continue;
            }

            if (!$generator instanceof DependsOn) {
                continue;
            }

            foreach ($generator->dependsOnGenerator() as $className) {
                if (!in_array($className, $classNames, true)) {
                    $issues[] = "Generator " . $generator::class . " depends on {$className} which is not registered.";
                }
            }
        }

        if (!empty($issues)) {
            $this->error("Found issues with the generators:");
            foreach ($issues as $issue) {
                $this->info($issue);
            }
            return null;
        }

        return $allGenerators;
    }

    /**
     * @param list<GeneratesOperationCode|GeneratesLibFiles> $generators
     * @param list<OperationData> $definitions
     * @param GeneralMetadata $metadata
     * @return array<string, TsFile>
     */
    private function generateLibFiles(array $generators, array $definitions, GeneralMetadata $metadata): array
    {
        return array_reduce(
            $generators,
            function (array $carry, $codeGenerator) use ($definitions, $metadata): array {
                if (!$codeGenerator instanceof GeneratesLibFiles) {
                    return $carry;
                }

                foreach ($codeGenerator->emitFiles($definitions, $metadata) as $fileName => $fileContent) {
                    $carry[$fileName] ??= new TsFile();
                    $carry[$fileName]->merge(TsFile::fromString($fileContent));
                }
                return $carry;
            },
            []
        );
    }

    private function generateAllErrorTypes(Server $server, Definition $operation): string
    {
        // This could be moved to AST
        $possibleTypes = Arrays::filterNullValues(array_map(function (ExceptionPresenter $presenter) use ($operation): null|string {
            $code = $presenter::errorType();
            $details = $presenter->toTypeScriptDefinition($operation);
            return $details === null ? null : "{code: {$code->value}, details: {$details}}";
        }, [...$server->exceptionPresenters, $server->defaultPresenter]));

        // @phpstan-ignore-next-line empty.variable
        return empty($possibleTypes) ? 'never' : implode('|', $possibleTypes);
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