<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands;

use Illuminate\Console\Command;
use Le0daniel\PhpTsBindings\Operations\Contracts\OperationRegistry;

final class ListCommand extends Command
{
    protected $signature = 'operations:list';
    protected $description = 'Send a marketing email to a user';
    public function handle(OperationRegistry $registry): int {
        return 0;
    }
}