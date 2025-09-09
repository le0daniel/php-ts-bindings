<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Contracts;

use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\CodeGen\Helpers\TypeScriptFile;

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
     * @param list<TypedOperation> $operations
     * @return array<string, string|TypeScriptFile>
     */
    public function emitFiles(array $operations, ServerMetadata $metadata): array;
}