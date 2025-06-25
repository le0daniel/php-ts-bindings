<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Operations;

use Closure;
use Le0daniel\PhpTsBindings\Discovery\DiscoveryManager;
use Le0daniel\PhpTsBindings\Discovery\OperationDiscovery;
use Le0daniel\PhpTsBindings\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Operations\Data\Endpoint;
use Le0daniel\PhpTsBindings\Operations\Data\OperationDefinition;
use Le0daniel\PhpTsBindings\Parser\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\Data\ParsingContext;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Reflection\TypeReflector;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use ReflectionClass;
use ReflectionException;

final class JustInTimeDiscoveryRegistry implements OperationRegistry
{
    private bool $isLoaded = false;
    private OperationDiscovery $discovery;

    public function __construct(
        private readonly string     $directory,
        private readonly TypeParser $parser,
    )
    {
        $this->discovery = new OperationDiscovery();
    }

    private function ensureIsDiscovered(): void
    {
        if (!$this->isLoaded) {
            new DiscoveryManager([$this->discovery,])->discover($this->directory);
            $this->isLoaded = true;
        }
    }

    public function has(string $type, string $fullyQualifiedKey): bool
    {
        $this->ensureIsDiscovered();
        return match ($type) {
            'command' => array_key_exists($fullyQualifiedKey, $this->discovery->commands),
            'query' => array_key_exists($fullyQualifiedKey, $this->discovery->queries),
        };
    }

    /**
     * @throws ReflectionException
     */
    public function get(string $type, string $fullyQualifiedKey): Endpoint
    {
        $this->ensureIsDiscovered();
        $definition = match ($type) {
            'command' => $this->discovery->commands[$fullyQualifiedKey],
            'query' => $this->discovery->queries[$fullyQualifiedKey],
        };

        $classReflection = new ReflectionClass($definition->fullyQualifiedClassName);
        $inputParameter = $classReflection->getMethod($definition->methodName)->getParameters()[0];

        $parsingContext = ParsingContext::fromClassReflection($classReflection);
        $input = fn() => $this->parser->parse(TypeReflector::reflectParameter($inputParameter), $parsingContext);
        $output = fn() => $this->parser->parse(TypeReflector::reflectReturnType($classReflection->getMethod($definition->methodName)), $parsingContext);

        return new Endpoint($definition, $input, $output);
    }

    public function writeToCache(string $filePath): void
    {
        $this->ensureIsDiscovered();

        /** @var OperationDefinition[] $allOperations */
        $allOperations = [...$this->discovery->queries, ...$this->discovery->commands];
        $endpointClass = PHPExport::absolute(Endpoint::class);

        $endpoints = [];
        $asts = [];
        foreach ($allOperations as $operation) {
            $endpoint = $this->get($operation->type, $operation->fullyQualifiedName());
            $inputAstName = "{$operation->type}:{$endpoint->definition->fullyQualifiedClassName}#input";
            $outputAstName = "{$operation->type}:{$endpoint->definition->fullyQualifiedClassName}#output";

            $asts[$inputAstName] = $endpoint->inputNode(...);
            $asts[$outputAstName] = $endpoint->outputNode(...);

            $exportedDefinition = $endpoint->definition->exportPhpCode();
            $endpoints["{$operation->type}.{$operation->fullyQualifiedName()}"] =
                "fn() => new {$endpointClass}($exportedDefinition, fn() => \$typeRegistry->get('{$inputAstName}'), fn() => \$typeRegistry->get('{$outputAstName}'))";
        }

        $optimizer = new AstOptimizer();
        $operationRegistryClass = PHPExport::absolute(CachedOperationRegistry::class);

        $endpointsCode = implode(',', $endpoints);

        file_put_contents($filePath, <<<PHP
<?php declare(strict_types=1);

\$typeRegistry = {$optimizer->generateOptimizedCode($asts)};    
return new {$operationRegistryClass}([{$endpointsCode}]);
PHP);
    }

    /**
     * @throws ReflectionException
     */
    public function all(): array
    {
        $this->ensureIsDiscovered();
        /** @var OperationDefinition[] $allOperations */
        $allOperations = [...$this->discovery->queries, ...$this->discovery->commands];
        return array_map( fn(OperationDefinition $operation) => $this->get($operation->type, $operation->fullyQualifiedName()),
            $allOperations
        );
    }
}