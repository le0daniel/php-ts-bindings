<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators;

use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\GeneralMetadata;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\OperationCode;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\OperationData;

interface GeneratesOperationCode
{
    /**
     * @param OperationData $operation
     * @param GeneralMetadata $metadata
     * @return OperationCode|null
     */
    public function generateOperationCode(OperationData $operation, GeneralMetadata $metadata): ?OperationCode;
}