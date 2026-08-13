<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Operations;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Executor\Exceptions\SchemaException;
use Le0daniel\PhpTsBindings\Parser\Helpers\ASTOptimizer;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\OperationNotFoundException;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

/**
 * Serves operations from generated code, memoizing each one.
 *
 * Like CachedTypeRegistry, the factory is a single closure wrapping a match over every key rather
 * than an array holding one closure per key: the array form allocates one Closure per operation on
 * every require of the cache file, while a match arm costs nothing until its key is requested. The
 * key table backing has() is a plain literal, which opcache shares across requests.
 */
final class CachedOperationRegistry implements OperationRegistry
{
    /**
     * @var array<string, Operation>
     */
    private array $instances = [];

    /**
     * @var Closure(string): Operation
     */
    private readonly Closure $factory;

    /**
     * @param  Closure(string): Operation|array<string, mixed>  $factory  The array form is the
     *                                                                    one-closure-per-operation format written by older builds and is rejected:
     *                                                                    this registry answers has() from the key table, which that format never wrote.
     * @param  array<string, true>  $keys  Defaults to empty only so a legacy cache reaches the
     *                                     descriptive rejection above instead of an ArgumentCountError.
     */
    public function __construct(
        Closure|array $factory,
        private readonly array $keys = [],
    ) {
        if (! $factory instanceof Closure) {
            throw new SchemaException(
                'The operations cache holds one closure per operation, the format written by an '
                .'older build, and cannot be read. Regenerate the operations cache.',
            );
        }

        $this->factory = $factory;
    }

    #[Override]
    public function has(OperationType $type, string $key): bool
    {
        return array_key_exists(
            $type->fullyQualifiedOperationKey($key),
            $this->keys
        );
    }

    #[Override]
    public function get(OperationType $type, string $key): Operation
    {
        $key = $type->fullyQualifiedOperationKey($key);

        return $this->instances[$key] ??= ($this->factory)($key);
    }

    #[Override]
    public function all(): array
    {
        foreach ($this->keys as $key => $present) {
            $this->instances[$key] ??= ($this->factory)($key);
        }

        return $this->instances;
    }

    public static function toPhpCode(
        OperationRegistry $registry,
        int $idLength,
    ): string {
        $endpointClass = PHPExport::absolute(Operation::class);

        $arms = [];
        $keys = [];
        $asts = [];
        foreach ($registry->all() as $endpoint) {
            $operation = $endpoint->definition;

            $inputAstName = "{$operation->type->name}:{$operation->fullyQualifiedName()}#input";
            $outputAstName = "{$operation->type->name}:{$operation->fullyQualifiedName()}#output";

            $asts[$inputAstName] = $endpoint->inputNode(...);
            $asts[$outputAstName] = $endpoint->outputNode(...);

            $exportedDefinition = $endpoint->definition->exportPhpCode();

            // The key is computed based on the endpoint key from the operation registry provided.
            $key = $operation->type->fullyQualifiedOperationKey($endpoint->key);

            $arms[] =
                "'{$key}' => new {$endpointClass}('{$endpoint->key}', $exportedDefinition, fn() => \$typeRegistry->get('{$inputAstName}'), fn() => \$typeRegistry->get('{$outputAstName}')),";
            $keys[] = "'{$key}' => true,";
        }

        // The ast optimizer deduplicates all the ASTs, minimizing the nodes required at runtime.
        $optimizer = new ASTOptimizer(
            idLength: $idLength,
        );
        $operationRegistryClass = PHPExport::absolute(CachedOperationRegistry::class);
        $notFoundException = PHPExport::absolute(OperationNotFoundException::class);

        // Operation discovery order depends on the filesystem, so sorting by key is what makes the
        // generated artifact byte identical across machines.
        ksort($asts);
        sort($arms);
        sort($keys);
        $armsCode = implode(PHP_EOL, $arms);
        $keysCode = implode('', $keys);

        return <<<PHP
\$typeRegistry = {$optimizer->generateOptimizedCode($asts)};
return new {$operationRegistryClass}(
    static fn (string \$key): {$endpointClass} => match (\$key) {
{$armsCode}
        default => throw {$notFoundException}::forKey(\$key),
    },
    [{$keysCode}],
);
PHP;
    }

    public static function writeToCache(OperationRegistry $registry, string $filePath, int $idLength): void
    {
        $code = self::toPhpCode($registry, $idLength);

        // The cached code binds both the Asts and operations together and creates a file
        // that can be required with fully compiled types.
        PHPExport::writeFileAtomically(
            $filePath,
            <<<PHP
<?php declare(strict_types=1);

{$code}
PHP
        );
    }
}
