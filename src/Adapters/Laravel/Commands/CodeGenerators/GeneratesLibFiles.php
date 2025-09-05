<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators;

use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\GeneralMetadata;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\OperationData;

interface GeneratesLibFiles
{
    /**
     * Emit files with shared functionality. No need to add .ts
     * All those files are generated in the lib folder to not interfere with operation definitions
     *
     * [
     *   'fileName' => 'content'
     * ]
     *
     * @param list<OperationData> $operations
     * @return array<string, string|TsFile>
     */
    public function emitFiles(array $operations, GeneralMetadata $metadata): array;
}