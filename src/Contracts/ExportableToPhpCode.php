<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

/**
 * @internal
 */
interface ExportableToPhpCode
{
    public function exportPhpCode(): string;
}
