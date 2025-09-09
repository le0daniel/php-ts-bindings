<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Operations;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Parser\ASTOptimizer;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
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

    public function has(OperationType $type, string $fullyQualifiedKey): bool
    {
        return array_key_exists(
            self::key($type, $fullyQualifiedKey),
            $this->operations
        );
    }

    public function get(OperationType $type, string $fullyQualifiedKey): Operation
    {
        $key = self::key($type, $fullyQualifiedKey);
        return $this->instances[$key] ??= $this->operations[$key]();
    }

    private static function key(OperationType $type, string $fullyQualifiedKey): string
    {
        return "{$type->name}:{$fullyQualifiedKey}";
    }

    public function all(): array
    {
        foreach ($this->operations as $key => $factory) {
            $this->instances[$key] ??= $factory();
        }
        return $this->instances;
    }

    public static function writeToCache(OperationRegistry $registry, string $filePath): void
    {
        $endpointClass = PHPExport::absolute(Operation::class);

        $endpoints = [];
        $asts = [];
        foreach ($registry->all() as $endpoint) {
            $operation = $endpoint->definition;

            $inputAstName = "{$operation->type->name}:{$operation->fullyQualifiedName()}#input";
            $outputAstName = "{$operation->type->name}:{$operation->fullyQualifiedName()}#output";

            $asts[$inputAstName] = $endpoint->inputNode(...);
            $asts[$outputAstName] = $endpoint->outputNode(...);

            $exportedDefinition = $endpoint->definition->exportPhpCode();
            $key = self::key($operation->type, $operation->fullyQualifiedName());

            $endpoints[] =
                "'{$key}' => fn() => new {$endpointClass}('{$endpoint->key}', $exportedDefinition, fn() => \$typeRegistry->get('{$inputAstName}'), fn() => \$typeRegistry->get('{$outputAstName}'))";
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