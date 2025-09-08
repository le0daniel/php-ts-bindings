<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Operations;

use Le0daniel\PhpTsBindings\Contracts\Discoverer;
use Le0daniel\PhpTsBindings\Reflection\FileReflector;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final readonly class DiscoveryManager
{

    /**
     * @param list<Discoverer> $discoverers
     */
    public function __construct(
        private array $discoverers,
    )
    {
        
    }
    
    public function discover(string $directory): void {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $reflection = new FileReflector($file->getRealPath());
            $class = $reflection->getDeclaredClass();

            foreach ($this->discoverers as $discoverer) {
                $discoverer->discover($class);
            }
        }
    }
}