<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Contracts;

use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;

interface GeneratesOperationCode
{
    /**
     * The code this generator contributes for one operation, with the imports it relies on, or null
     * when the operation is none of its business. The file it is appended to merges the imports and
     * places the blank lines around the block.
     */
    public function generateOperationCode(TypedOperation $operation, ServerMetadata $metadata): ?TypescriptFile;
}