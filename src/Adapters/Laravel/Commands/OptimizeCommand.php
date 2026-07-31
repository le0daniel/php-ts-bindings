<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelServiceProvider;
use Le0daniel\PhpTsBindings\Executor\Exceptions\SchemaException;
use Le0daniel\PhpTsBindings\Server\Operations\CachedOperationRegistry;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedOperationRegistry;
use Throwable;

final class OptimizeCommand extends Command
{
    protected $signature = 'operations:optimize {--id-length=}';
    protected $description = 'Optimize the schema operations for production use';

    public function handle(Application $application): int
    {
        // Always use a fresh server with eagerly loaded schema.
        // Otherwise the types
        $server = LaravelServiceProvider::serverFactory(
            $application,
            operations: null,
        );

        $registry = $server->registry;

        if (!$registry instanceof EagerlyLoadedOperationRegistry) {
            throw new SchemaException('Cannot optimize a registry that is not a JustInTimeDiscoveryRegistry');
        }

        $idLength = $this->hasOption('id-length')
            ? (int) $this->option('id-length')
            : config('operations.cache.idLength');

        if (!is_int($idLength) || $idLength < 1) {
            throw new SchemaException('Invalid id-length option');
        }

        try {
            CachedOperationRegistry::writeToCache(
                $registry,
                base_path('bootstrap/cache/operations.php'),
                idLength: (int) $this->option('id-length'),
            );
            require base_path('bootstrap/cache/operations.php');
        } catch (Throwable $e) {
            unlink(base_path('bootstrap/cache/operations.php'));
            return 1;
        }

        return 0;
    }
}