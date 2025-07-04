<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Operations;

use Closure;
use Le0daniel\PhpTsBindings\Discovery\DiscoveryManager;
use Le0daniel\PhpTsBindings\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Operations\Data\Operation;
use Le0daniel\PhpTsBindings\Parser\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\Data\ParsingContext;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Reflection\TypeReflector;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use ReflectionClass;
use ReflectionException;

final class JustInTimeDiscoveryRegistry implements OperationRegistry
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
     * @param TypeParser $parser
     * @param string|string[] $directories
     * @return self
     */
    public static function eagerlyDiscover(TypeParser $parser, string|array $directories): self
    {
        $directories = is_array($directories) ? $directories : [$directories];
        $collector = new OperationDiscovery();
        $discoverer = new DiscoveryManager([$collector]);
        foreach ($directories as $directory) {
            $discoverer->discover($directory);
        }

        $factories = [];
        foreach ([...$collector->queries, ...$collector->commands] as $definition) {
            $factories["{$definition->type}@{$definition->fullyQualifiedName()}"] = static function () use ($definition, $parser) {
                $classReflection = new ReflectionClass($definition->fullyQualifiedClassName);
                $inputParameter = $classReflection->getMethod($definition->methodName)->getParameters()[0];

                $parsingContext = ParsingContext::fromClassReflection($classReflection);
                $input = fn() => $parser->parse(TypeReflector::reflectParameter($inputParameter), $parsingContext);
                $output = fn() => $parser->parse(TypeReflector::reflectReturnType($classReflection->getMethod($definition->methodName)), $parsingContext);

                return new Operation($definition, $input, $output);
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
        return array_values($this->instances);
    }
}