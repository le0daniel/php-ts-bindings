<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Container\Attributes\Give;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelServiceProvider;
use Le0daniel\PhpTsBindings\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Server\Operations\CachedOperationRegistry;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedRegistry;
use Le0daniel\PhpTsBindings\Server\Server;
use RuntimeException;

final class OptimizeCommand extends Command
{
    protected $signature = 'operations:optimize';
    protected $description = 'Optimize the schema operations for production use';

    public function handle(#[Give(LaravelServiceProvider::DEFAULT_SERVER)] Server $server): int
    {
        $registry = $server->registry;

        if (!$registry instanceof EagerlyLoadedRegistry) {
            throw new RuntimeException('Cannot optimize a registry that is not a JustInTimeDiscoveryRegistry');
        }

        try {
            CachedOperationRegistry::writeToCache($registry, base_path('bootstrap/cache/operations.php'));
            require base_path('bootstrap/cache/operations.php');
        } catch (\Throwable $e) {
            unlink(base_path('bootstrap/cache/operations.php'));
            return 1;
        }

        return 0;
    }
}