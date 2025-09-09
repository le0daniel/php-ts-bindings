<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands;

use Closure;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Container\Attributes\Give;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelHttpController;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelServiceProvider;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Utils\ArtisanOptions;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperationClientBindings;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperations;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitQueryKey;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTanstackQuery;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypeMap;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypes;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypeUtils;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\GeneratesOperationCode;
use Le0daniel\PhpTsBindings\CodeGen\Exceptions\InvalidGeneratorDependencies;
use Le0daniel\PhpTsBindings\CodeGen\Helpers\TypeScriptFile;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptDefinitionGenerator;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptServerCodeGenerator;
use Le0daniel\PhpTsBindings\Server\Server;
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

    /**
     * @throws BindingResolutionException
     */
    public function handle(
        #[Give(LaravelServiceProvider::DEFAULT_SERVER)] Server $server,
        Router                                                 $router,
        Application                                            $application,
        TypescriptDefinitionGenerator                          $typescriptGenerator
    ): int
    {
        try {
            $metadata = new ServerMetadata(
                $router->getRoutes()->getByName(LaravelHttpController::QUERY_NAME)->uri(),
                $router->getRoutes()->getByName(LaravelHttpController::COMMAND_NAME)->uri(),
            );

            $codeGenerator = new TypescriptServerCodeGenerator(
                $this->getGeneratorsFromInput($application),
                $typescriptGenerator
            );

            $files = $codeGenerator->generate(
                $server,
                $metadata,
                ArtisanOptions::expandOptionsArrayCommaSeparated($this->option('ignore'))
            );
        } catch (InvalidGeneratorDependencies $exception) {
            $this->error($exception->getMessage());
            foreach ($exception->messages as $message) {
                $this->error($message);
            }
            return 1;
        }

        $directory = str_starts_with('/', $this->argument('directory'))
            ? $this->argument('directory')
            : base_path($this->argument('directory'));

        if ($this->option('verify')) {
            $this->info("Verify generated code only.");
            return $this->verifyContentOnly($directory, $files);
        }

        $this->writeFiles($directory, $files);
        return 0;
    }

    /**
     * @return Closure(TypedOperation): string
     * @throws BindingResolutionException
     */
    private function getNamingGenerator(Application $application): Closure
    {
        $nameGenerator = match ($this->option('naming')) {
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
     * @param array<string, TypeScriptFile> $files
     * @return Generator<string, TypeScriptFile, mixed, void>
     */
    private function iterateFiles(string $directory, array $files): Generator
    {
        foreach ($files as $fileName => $file) {
            $filePath = "{$directory}/{$fileName}";
            yield $filePath => $file;
        }
    }

    /**
     * @param string $directory
     * @param array<string, TypeScriptFile> $files
     * @return int
     */
    private function verifyContentOnly(string $directory, array $files): int
    {
        $issues = [];
        foreach ($this->iterateFiles($directory, $files) as $filePath => $file) {
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
     * @param array<string, TypeScriptFile> $files
     * @return void
     */
    private function writeFiles(string $directory, array $files): void
    {
        $this->clearDirectory($directory);

        if (!file_exists("{$directory}/lib") && is_dir("{$directory}/lib") === false) {
            mkdir("{$directory}/lib", 0777, true);
        }

        foreach ($this->iterateFiles($directory, $files) as $filePath => $file) {
            file_put_contents($filePath, $file->toString());
        }
    }

    /**
     * @return list<GeneratesOperationCode|GeneratesLibFiles>
     * @throws BindingResolutionException
     */
    private function getGeneratorsFromInput(Application $application): array
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
        return array_values([
            ...$generators,
            ...$customGenerators,
        ]);
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