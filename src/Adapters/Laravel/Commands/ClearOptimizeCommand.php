<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands;

use Illuminate\Console\Command;

final class ClearOptimizeCommand extends Command
{
    protected $signature = 'operations:clear-optimize';
    protected $description = 'Clears the optimizations';

    public function handle(): int
    {
        unlink(base_path('bootstrap/cache/operations.php'));
        return 0;
    }
}