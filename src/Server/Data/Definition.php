<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Le0daniel\PhpTsBindings\Contracts\ExportableToPhpCode;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

final readonly class Definition implements ExportableToPhpCode
{
    /**
     * @param  class-string<object>  $fullyQualifiedClassName
     * @param  list<class-string<MiddlewareContract<mixed>>>  $middleware
     */
    public function __construct(
        public OperationType $type,
        public string $fullyQualifiedClassName,
        public string $methodName,
        public string $name,
        public string $namespace,
        public array $middleware,
    ) {
    }

    public function fullyQualifiedName(): string
    {
        return "{$this->namespace}.{$this->name}";
    }

    #[Override]
    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $type = PHPExport::export($this->type);
        $fullyQualifiedClassName = PHPExport::export($this->fullyQualifiedClassName);
        $methodName = PHPExport::export($this->methodName);
        $name = PHPExport::export($this->name);
        $namespace = PHPExport::export($this->namespace);
        $middleware = PHPExport::exportArray($this->middleware);

        // Descriptions are ignored when caching.
        return "new {$className}({$type}, {$fullyQualifiedClassName}, {$methodName}, {$name}, {$namespace}, {$middleware})";
    }
}
