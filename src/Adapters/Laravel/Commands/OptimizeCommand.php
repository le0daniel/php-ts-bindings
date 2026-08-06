<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelServiceProvider;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Utils\ArtisanOptions;
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

        if (! $registry instanceof EagerlyLoadedOperationRegistry) {
            throw new SchemaException('Cannot optimize a registry that is not an EagerlyLoadedOperationRegistry');
        }

        $idLength = ArtisanOptions::asPositiveInt(
            $this->option('id-length'),
            config('operations.cache.idLength'),
        );

        if ($idLength === null) {
            $this->error('The id-length must be a positive integer. Pass --id-length or set operations.cache.idLength.');

            return 1;
        }

        $cacheFile = base_path('bootstrap/cache/operations.php');

        try {
            CachedOperationRegistry::writeToCache($registry, $cacheFile, idLength: $idLength);

            // Requiring the file proves it parses and that the ids it generated do not collide,
            // rather than leaving a broken cache behind for the next request to trip over.
            require $cacheFile;
        } catch (Throwable $e) {
            $this->error("Failed to optimize operations: {$e->getMessage()}");

            // The write may never have happened, in which case there is nothing to clean up.
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }

            return 1;
        }

        return 0;
    }
}
