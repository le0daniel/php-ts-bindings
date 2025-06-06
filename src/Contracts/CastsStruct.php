<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

interface CastsStruct extends ExportableToPhpCode
{
    public function castToStruct(array $validatedProperties): object;
}