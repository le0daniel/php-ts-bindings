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

    public static function toPhpCode(OperationRegistry $registry): string
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

            // The key is computed based on the endpoint key from the operation registry provided.
            $key = self::key($operation->type, $endpoint->key);

            $endpoints[] =
                "'{$key}' => fn() => new {$endpointClass}('{$endpoint->key}', $exportedDefinition, fn() => \$typeRegistry->get('{$inputAstName}'), fn() => \$typeRegistry->get('{$outputAstName}'))";
        }

        // The ast optimizer is used to deduplicate all the ASTs, minimizing the nodes required.
        // Additional optimizations are performed on structs and unions for faster execution.
        $optimizer = new AstOptimizer();
        $operationRegistryClass = PHPExport::absolute(CachedOperationRegistry::class);

        $endpointsCode = implode(',', $endpoints);

        return <<<PHP
\$typeRegistry = {$optimizer->generateOptimizedCode($asts)};    
return new {$operationRegistryClass}([{$endpointsCode}]);
PHP;
    }

    public static function writeToCache(OperationRegistry $registry, string $filePath): void
    {
        $code = self::toPhpCode($registry);

        // The cached code binds both the Asts and operations together and creates a file
        // that can be required with fully compiled types.
        file_put_contents($filePath, <<<PHP
<?php declare(strict_types=1);

{$code}
PHP
        );
    }
}