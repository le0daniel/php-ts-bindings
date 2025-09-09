<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\TypedOperation;
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