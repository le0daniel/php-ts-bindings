<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Contracts;

use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;

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
     * @param  list<TypedOperation>  $operations
     * @param  AliasRegistry  $registry  The run's shared registry: every alias any operation produced.
     * @return array<string, TypescriptFile>
     */
    public function emitFiles(array $operations, ServerMetadata $metadata, AliasRegistry $registry): array;
}
