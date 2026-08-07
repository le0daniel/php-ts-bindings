<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Operations;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\OperationKeyGenerator;
use Le0daniel\PhpTsBindings\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Executor\Exceptions\SchemaException;
use Le0daniel\PhpTsBindings\Parser\Helpers\ParsingScope;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Reflection\FileReflector;
use Le0daniel\PhpTsBindings\Reflection\TypeReflector;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\HashSha256KeyGenerator;
use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use SplFileInfo;

final class EagerlyLoadedOperationRegistry implements OperationRegistry
{
    /**
     * @var array<string, Operation>
     */
    private array $instances = [];

    /**
     * @param  array<string, Closure(): Operation>  $factories
     */
    public function __construct(
        private readonly array $factories,
    ) {
    }

    /**
     * @param  string|string[]  $directories
     */
    public static function eagerlyDiscover(
        string|array $directories,
        TypeParser $parser = new TypeParser(),
        OperationKeyGenerator $keyGenerator = new HashSha256KeyGenerator('default', 8, 24),
        OperationDiscovery $discovery = new OperationDiscovery(),
    ): self {
        $directories = is_array($directories) ? $directories : [$directories];
        foreach ($directories as $directory) {
            self::discoverDirectory($directory, $discovery);
        }

        return self::registryFromDiscovery($parser, $keyGenerator, $discovery);
    }

    /**
     * Every .php file under $directory is reflected and offered to the discovery, which keeps the
     * ones carrying #[Query] or #[Command].
     */
    private static function discoverDirectory(string $directory, OperationDiscovery $discovery): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! $file->getRealPath()) {
                continue;
            }

            $discovery->discover(new FileReflector($file->getRealPath())->getDeclaredClass());
        }
    }

    private static function registryFromDiscovery(
        TypeParser $parser,
        OperationKeyGenerator $keyGenerator,
        OperationDiscovery $discovery,
    ): self {
        $factories = [];
        foreach ($discovery->operations as $definition) {
            $key = $keyGenerator->generateKey($definition->namespace, $definition->name);
            $fullyQualifiedKey = $definition->type->registryKey($key);

            // Keys can be truncated hashes, so two operations can collide on one. Assigning over
            // the entry would leave an operation silently unreachable; ASTOptimizer throws for the
            // same reason.
            if (array_key_exists($fullyQualifiedKey, $factories)) {
                throw new SchemaException(
                    "Operation key collision on '{$key}' for {$definition->fullyQualifiedName()}. "
                    ."Two operations hash to the same key - increase the key generator's length."
                );
            }

            // Lazily execute the parsing.
            $factories[$fullyQualifiedKey] = static function () use ($definition, $parser, $key) {
                $classReflection = new ReflectionClass($definition->fullyQualifiedClassName);
                $method = $classReflection->getMethod($definition->methodName);
                $inputParameter = $method->getParameters()[0];

                // A class can register a method it inherited, whose PHPDoc was written against a
                // different file's namespace and imports. @param and @return share that one
                // docblock, so both resolve in the file the method is written in.
                $parsingContext = ParsingScope::fromReflectionClass($classReflection)
                    ->descendIntoDeclaringFileOf($method);

                $input = fn () => $parser->parse(TypeReflector::reflectParameter($inputParameter), $parsingContext);
                $output = fn () => $parser->parse(TypeReflector::reflectReturnType($method), $parsingContext);

                return new Operation($key, $definition, $input, $output);
            };
        }

        return new self($factories);
    }

    /**
     * @param  list<class-string>  $classes
     *
     * @throws ReflectionException
     */
    public static function withClasses(
        array $classes,
        TypeParser $parser = new TypeParser(),
        OperationKeyGenerator $keyGenerator = new HashSha256KeyGenerator('default', 8, 24),
        OperationDiscovery $discovery = new OperationDiscovery(),
    ): self {
        foreach ($classes as $className) {
            $discovery->discover(new ReflectionClass($className));
        }

        return self::registryFromDiscovery($parser, $keyGenerator, $discovery);
    }

    #[Override]
    public function has(OperationType $type, string $fullyQualifiedKey): bool
    {
        $key = $type->registryKey($fullyQualifiedKey);

        return array_key_exists($key, $this->factories);
    }

    /**
     * @throws ReflectionException
     */
    #[Override]
    public function get(OperationType $type, string $fullyQualifiedKey): Operation
    {
        $key = $type->registryKey($fullyQualifiedKey);

        return $this->instances[$key] ??= $this->factories[$key]();
    }

    /**
     * @return array<string, Operation>
     */
    #[Override]
    public function all(): array
    {
        foreach ($this->factories as $key => $factory) {
            $this->instances[$key] ??= $factory();
        }

        return $this->instances;
    }
}
