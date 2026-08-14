<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Le0daniel\PhpTsBindings\Contracts\ExportableToPhpCode;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

/**
 * One #[Middleware] declaration on an operation: the class to run and the config it carries.
 * The config shape is validated where declarations enter the system - OperationDiscovery - so
 * instances built from a cache file are trusted as-is.
 */
final readonly class MiddlewareDefinition implements ExportableToPhpCode
{
    /**
     * @param  class-string<MiddlewareContract<mixed>>  $middleware
     * @param  array<string, scalar>  $config
     */
    public function __construct(
        public string $middleware,
        public array $config = [],
    ) {
    }

    #[Override]
    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $middleware = PHPExport::export($this->middleware);

        if ($this->config === []) {
            return "new {$className}({$middleware})";
        }

        $entries = [];
        foreach ($this->config as $key => $value) {
            $entries[] = var_export($key, true).' => '.var_export($value, true);
        }
        $config = '['.implode(', ', $entries).']';

        return "new {$className}({$middleware}, {$config})";
    }
}
