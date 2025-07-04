<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands;

use Illuminate\Console\Command;
use Le0daniel\PhpTsBindings\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Operations\JustInTimeDiscoveryRegistry;
use RuntimeException;

final class OptimizeCommand extends Command
{
    protected $signature = 'operations:optimize';
    protected $description = 'Optimize the schema operations for production use';

    public function handle(OperationRegistry $registry): int
    {
        if (!$registry instanceof JustInTimeDiscoveryRegistry) {
            throw new RuntimeException('Cannot optimize a registry that is not a JustInTimeDiscoveryRegistry');
        }

        try {
            $registry->writeToCache(base_path('bootstrap/cache/operations.php'));
            require base_path('bootstrap/cache/operations.php');
        } catch (\Throwable $e) {
            unlink(base_path('bootstrap/cache/operations.php'));
        }

        return 0;
    }
}