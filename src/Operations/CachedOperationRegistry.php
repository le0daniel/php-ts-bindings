<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Operations;

use Closure;
use Le0daniel\PhpTsBindings\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Operations\Data\Operation;
use Le0daniel\PhpTsBindings\Parser\ASTOptimizer;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final class CachedOperationRegistry implements OperationRegistry
{
    /**
     * @var array<string, Operation>
     */
    private array $instances = [];

    /**
     * @param array<string, Closure(): Operation> $operations
     */
    public function __construct(private readonly array $operations)
    {
    }

    public function has(string $type, string $fullyQualifiedKey): bool
    {
        return array_key_exists("{$type}:{$fullyQualifiedKey}", $this->operations);
    }

    public function get(string $type, string $fullyQualifiedKey): Operation
    {
        $key = "{$type}:{$fullyQualifiedKey}";
        return $this->instances[$key] ??= $this->operations[$key]();
    }

    public function all(): array
    {
        foreach ($this->operations as $key => $factory) {
            $this->instances[$key] ??= $factory();
        }
        return array_values($this->instances);
    }

    public static function writeToCache(OperationRegistry $registry, string $filePath): void
    {
        $endpointClass = PHPExport::absolute(Operation::class);

        $endpoints = [];
        $asts = [];
        foreach ($registry->all() as $endpoint) {
            $operation = $endpoint->definition;

            $inputAstName = "{$operation->type}:{$endpoint->definition->fullyQualifiedClassName}#input";
            $outputAstName = "{$operation->type}:{$endpoint->definition->fullyQualifiedClassName}#output";

            $asts[$inputAstName] = $endpoint->inputNode(...);
            $asts[$outputAstName] = $endpoint->outputNode(...);

            $exportedDefinition = $endpoint->definition->exportPhpCode();
            $endpoints[] =
                "'{$operation->type}:{$operation->fullyQualifiedName()}' => fn() => new {$endpointClass}($exportedDefinition, fn() => \$typeRegistry->get('{$inputAstName}'), fn() => \$typeRegistry->get('{$outputAstName}'))";
        }

        $optimizer = new AstOptimizer();
        $operationRegistryClass = PHPExport::absolute(CachedOperationRegistry::class);

        $endpointsCode = implode(',', $endpoints);

        file_put_contents($filePath, <<<PHP
<?php declare(strict_types=1);

\$typeRegistry = {$optimizer->generateOptimizedCode($asts)};    
return new {$operationRegistryClass}([{$endpointsCode}]);
PHP
        );
    }
}