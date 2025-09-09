<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Operations;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\OperationKeyGenerator;
use Le0daniel\PhpTsBindings\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Parser\Data\ParsingContext;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Reflection\TypeReflector;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\HashSha256KeyGenerator;
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
     * @param string|string[] $directories
     * @param TypeParser $parser
     * @param OperationKeyGenerator $keyGenerator
     * @return self
     */
    public static function eagerlyDiscover(
        string|array          $directories,
        TypeParser            $parser = new TypeParser(),
        OperationKeyGenerator $keyGenerator = new HashSha256KeyGenerator('default', 8, 24),
        OperationDiscovery    $discovery = new OperationDiscovery(),
    ): self
    {
        $directories = is_array($directories) ? $directories : [$directories];
        $discoverer = new DiscoveryManager([$discovery]);
        foreach ($directories as $directory) {
            $discoverer->discover($directory);
        }

        $factories = [];
        foreach ($discovery->operations as $definition) {
            $key = $keyGenerator->generateKey($definition->namespace, $definition->name);
            $fullyQualifiedKey = self::key($definition->type, $key);

            // Lazily execute the parsing.
            $factories[$fullyQualifiedKey] = static function () use ($definition, $parser, $key) {
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

    private static function key(OperationType $type, string $fullyQualifiedKey): string {
        return "{$type->name}@{$fullyQualifiedKey}";
    }

    public function has(OperationType $type, string $fullyQualifiedKey): bool
    {
        $key = self::key($type, $fullyQualifiedKey);
        return array_key_exists($key, $this->factories);
    }

    /**
     * @throws ReflectionException
     */
    public function get(OperationType $type, string $fullyQualifiedKey): Operation
    {
        $key = self::key($type, $fullyQualifiedKey);
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