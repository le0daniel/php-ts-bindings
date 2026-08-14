<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelHttpController;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelServiceProvider;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Utils\ArtisanOptions;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesOperationCode;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\CodeGen\Exceptions\CodeGenException;
use Le0daniel\PhpTsBindings\CodeGen\Exceptions\InvalidGeneratorDependencies;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptServerCodeGenerator;
use Le0daniel\PhpTsBindings\CodeGen\Utils\OutputDirectory;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnsupportedTypeException;
use Le0daniel\PhpTsBindings\Utils\Assertions;

final class CodeGenCommand extends Command
{
    protected $signature = 'operations:codegen {directory} '
        .'{--with=* : tanstack-query | type-map} '
        .'{--custom=* : class-string<GeneratesLibFiles | GeneratesOperationCode>} '
        .'{--without=* : tanstack-query | type-map} '
        .'{--ignore=* : Ignored namespaces (namespace) or specific operations by specifying namespace.name} '
        .'{--naming=name : Naming mode to use. Modes: name, fqn, operation-prefix, namespace-postfix or classname::methodName for custom function}'
        .'{--verify} ';

    protected $description = 'Generate the typescript bindings for all operations';

    protected $help = <<<DESCRIPTION
Generate the typescript bindings for all operations
  Use --with=tanstack-query,... or --with=.* --with=.* to include a specific generators like tanstack-query operations.
  
  Following types are available:
    - types (default: true)
    - bindings (default: true)
    - utils (default: true)
    - operations-spa (default: true)
    - operations (default: true)
    - type-map (default: false)
    - tanstack-query (default: false)
    - query-key (default: false)

  A name given to both --with and --without is turned on: --with wins.

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
        Router $router,
        Application $application,
    ): int {
        // Always get a fresh server
        $server = LaravelServiceProvider::serverFactory(
            $application,
            operations: null,
        );

        $queryRoute = $router->getRoutes()->getByName(LaravelHttpController::QUERY_NAME);
        $commandRoute = $router->getRoutes()->getByName(LaravelHttpController::COMMAND_NAME);
        if ($queryRoute === null || $commandRoute === null) {
            $this->error(
                'The operation routes are not registered. Call LaravelHttpController::registerQueries() '
                .'and ::registerCommands() from your route definitions.'
            );

            return 1;
        }

        try {
            $metadata = new ServerMetadata(
                $queryRoute->uri(),
                $commandRoute->uri(),
                $server->configuration
            );

            $codeGenerator = new TypescriptServerCodeGenerator(
                $this->getGeneratorsFromInput($application),
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
        } catch (UnsupportedTypeException $exception) {
            // A schema that cannot be expressed in TypeScript is a bug worth surfacing here, rather
            // than a placeholder type that fails later inside the generated client.
            $this->error($exception->getMessage());

            return 1;
        } catch (CodeGenException $exception) {
            // A bad naming mode, a namespace that cannot be a file name, two operations generating
            // one name: all of them end the run with a message rather than a stack trace.
            $this->error($exception->getMessage());

            return 1;
        }

        $target = ArtisanOptions::asString($this->argument('directory')) ?? '';
        if ($target === '') {
            $this->error('A target directory is required.');

            return 1;
        }

        // Argument order matters: the haystack is the path. Reversed, this asked whether '/' starts
        // with the path, which is false for every real input, so absolute paths were being prefixed
        // with base_path().
        $directory = str_starts_with($target, '/') ? $target : base_path($target);

        if ($this->option('verify')) {
            $this->info('Verify generated code only.');

            return $this->verifyContentOnly($directory, $files);
        }

        try {
            OutputDirectory::write($directory, $files);
        } catch (CodeGenException $exception) {
            // Refusing to overwrite a file this library did not write.
            $this->error($exception->getMessage());

            return 1;
        }

        return 0;
    }

    /**
     * @param  array<string, TypescriptFile>  $files
     */
    private function verifyContentOnly(string $directory, array $files): int
    {
        $issues = OutputDirectory::verify($directory, $files);

        if (count($issues) > 0) {
            $count = count($issues);

            $this->error("Found {$count} issue(s):");
            foreach ($issues as $issue) {
                $this->info($issue);
            }

            return 1;
        }

        $this->line('All files are correct. No issues found.');

        return 0;
    }

    /**
     * @return list<GeneratesOperationCode|GeneratesLibFiles>
     *
     * @throws BindingResolutionException
     */
    private function getGeneratorsFromInput(Application $application): array
    {
        $with = ArtisanOptions::expandOptionsArrayCommaSeparated($this->option('with'));
        $without = ArtisanOptions::expandOptionsArrayCommaSeparated($this->option('without'));

        $namingGeneratorName = ($this->option('naming') ?? 'name') |> Assertions::string(...);

        $namingGenerator = match ($namingGeneratorName) {
            'fqn','operation-prefix','namespace-postfix','name' => CodeGenerators::namingGenerator($namingGeneratorName),
            default => $this->customNamingGenerator($application, $namingGeneratorName),
        };

        $defaultGenerators = CodeGenerators::fromDefaults(
            $namingGenerator,
            with: $with,
            without: $without,
        );

        $customGenerators = array_map(
            fn (string $className) => $application->make($className),
            ArtisanOptions::expandOptionsArrayCommaSeparated($this->option('custom'))
        );

        // @phpstan-ignore-next-line arrayValues.list
        return array_values([
            ...$defaultGenerators,
            ...$customGenerators,
        ]);
    }

    /**
     * Anything that is not one of the built-in modes is read as Class::method naming your own rule.
     * The class goes through the container and the method is called on the instance, despite the
     * static-looking syntax, so a rule is free to depend on whatever the container can build.
     *
     * @return Closure(TypedOperation): string
     *
     * @throws BindingResolutionException
     */
    private function customNamingGenerator(Application $application, string $naming): Closure
    {
        $parts = explode('::', $naming, 2);

        if (count($parts) === 2 && class_exists($parts[0]) && method_exists($parts[0], $parts[1])) {
            $instance = $application->make($parts[0]);

            /* @phpstan-ignore-next-line method.dynamicName */
            return $instance->{$parts[1]}(...);
        }

        // Thrown here rather than from inside the closure: getGeneratorsFromInput() runs inside
        // handle()'s try, so a typo ends the run with this message instead of a stack trace.
        throw new CodeGenException(
            "Unknown naming mode '{$naming}'. Use one of name, fqn, operation-prefix, "
            .'namespace-postfix, or Class::method naming your own rule.'
        );
    }
}
