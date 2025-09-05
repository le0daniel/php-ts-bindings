<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Operations;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Discovery\DiscoveryManager;
use Le0daniel\PhpTsBindings\Parser\Data\ParsingContext;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Reflection\TypeReflector;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Utils\Hashs;
use ReflectionClass;
use ReflectionException;

final class EagerlyLoadedRegistry implements OperationRegistry
{
    /**
     * @var array<string, Operation>
     */
    private array $instances = [];

    /**
     * @param array<string, Closure(): Operation> $factories
     */
    public function __construct(
        private readonly array $factories,
    )
    {
    }

    /**
     * Uses a deterministic hash with an optional pepper to generate stable obfuscated namespace and fnName map.
     *
     * @param string|null $pepper
     * @return Closure(Definition): string
     */
    public static function hashKeyGenerator(?string $pepper = null): Closure
    {
        return function (Definition $definition) use ($pepper): string {
            $pepper ??= 'default';

            $namespaceHash = Hashs::base64UrlEncodedSha256("{$definition->namespace}|{$pepper}");
            $fnHash = Hashs::base64UrlEncodedSha256("{$definition->name}|{$pepper}");

            $namespace = substr($namespaceHash, 0, 8);
            $fnName = substr($fnHash, 0, 24);

            return "{$namespace}.{$fnName}";
        };
    }

    /**
     * @return Closure(Definition): string
     */
    public static function plainKeyGenerator(): Closure
    {
        return function (Definition $definition): string {
            return "{$definition->namespace}.{$definition->name}";
        };
    }

    /**
     * @param string|string[] $directories
     * @param TypeParser $parser
     * @param Closure(Definition): string|null $keyGenerator
     * @return self
     */
    public static function eagerlyDiscover(
        string|array $directories,
        TypeParser   $parser = new TypeParser(),
        ?Closure     $keyGenerator = null,
    ): self
    {
        $keyGenerator ??= self::plainKeyGenerator();

        $directories = is_array($directories) ? $directories : [$directories];
        $collector = new OperationDiscovery();
        $discoverer = new DiscoveryManager([$collector]);
        foreach ($directories as $directory) {
            $discoverer->discover($directory);
        }

        $factories = [];
        foreach ([...$collector->queries, ...$collector->commands] as $definition) {
            $key = $keyGenerator($definition);

            // Lazily execute the parsing.
            $factories["{$definition->type}@{$key}"] = static function () use ($definition, $parser, $key) {
                $classReflection = new ReflectionClass($definition->fullyQualifiedClassName);
                $inputParameter = $classReflection->getMethod($definition->methodName)->getParameters()[0];

                $parsingContext = ParsingContext::fromReflectionClass($classReflection);
                $input = fn() => $parser->parse(TypeReflector::reflectParameter($inputParameter), $parsingContext);
                $output = fn() => $parser->parse(TypeReflector::reflectReturnType($classReflection->getMethod($definition->methodName)), $parsingContext);

                return new Operation($key, $definition, $input, $output);
            };
        }

        return new self($factories);
    }

    public function has(string $type, string $fullyQualifiedKey): bool
    {
        $key = "{$type}@{$fullyQualifiedKey}";
        return array_key_exists($key, $this->factories);
    }

    /**
     * @throws ReflectionException
     */
    public function get(string $type, string $fullyQualifiedKey): Operation
    {
        $key = "{$type}@{$fullyQualifiedKey}";
        return $this->instances[$key] ??= $this->factories[$key]();
    }

    /**
     * @return Operation[]
     */
    public function all(): array
    {
        foreach ($this->factories as $key => $factory) {
            $this->instances[$key] ??= $factory();
        }
        return $this->instances;
    }
}