<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands;

use App\Utils\Files;
use Illuminate\Console\Command;
use Le0daniel\PhpTsBindings\CodeGen\Data\DefinitionTarget;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptDefinitionGenerator;
use Le0daniel\PhpTsBindings\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Operations\JustInTimeDiscoveryRegistry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class CodeGenCommand extends Command
{
    protected $signature = 'operations:codegen {directory}';
    protected $description = 'Generate the typescript bindings for all operations';

    public function handle(OperationRegistry $registry): void
    {
        if (!$registry instanceof JustInTimeDiscoveryRegistry) {
            throw new RuntimeException('Cannot generate code for a registry that is not a JustInTimeDiscoveryRegistry');
        }

        $directory = str_starts_with('/', $this->argument('directory'))
            ? $this->argument('directory')
            : base_path($this->argument('directory'));

        $this->clearDirectory($directory);
        $generator = new TypescriptDefinitionGenerator();

        foreach ($registry->getAllByNamespace() as $namespace => $endpoints) {
            $definitions = [];
            foreach ($endpoints as $endpoint) {
                $definitions[] = match ($endpoint->definition->type) {
                    'query' => "export async function {$endpoint->definition->name}(): Promise<{$generator->toDefinition($endpoint->outputNode(), DefinitionTarget::OUTPUT)}>",
                    'command' => "export async function {$endpoint->definition->name}(): Promise<{$generator->toDefinition($endpoint->outputNode(), DefinitionTarget::OUTPUT)}>",
                };
            }
            file_put_contents("{$directory}/{$namespace}.ts", implode(PHP_EOL, $definitions) . PHP_EOL);
        }
    }

    private function clearDirectory(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isDir() || !str_ends_with($file->getBasename(), '.ts')) {
                continue;
            }

            $baseName = substr($file->getBasename(), 0, -strlen('.ts'));
            if (in_array($baseName, ['guards', 'utils'], true)) {
                continue;
            }

            unlink($file->getRealPath());
        }
    }
}