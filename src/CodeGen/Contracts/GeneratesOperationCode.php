<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Contracts;

use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\CodeGen\Helpers\TypescriptCodeBlock;

interface GeneratesOperationCode
{
    /**
     * @param TypedOperation $operation
     * @param ServerMetadata $metadata
     * @return TypescriptCodeBlock|null
     */
    public function generateOperationCode(TypedOperation $operation, ServerMetadata $metadata): ?TypescriptCodeBlock;
}