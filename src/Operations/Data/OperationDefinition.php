<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Operations\Data;

use Le0daniel\PhpTsBindings\Contracts\ClientAwareException;
use Le0daniel\PhpTsBindings\Contracts\ExportableToPhpCode;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final class OperationDefinition implements ExportableToPhpCode
{
    /**
     * @param 'command'|'query' $type
     * @param class-string<object> $fullyQualifiedClassName
     * @param string $methodName
     * @param string $name
     * @param string $namespace
     *
     * @param string|null $inputParameterName
     * @param string|null $description
     * @param list<class-string<ClientAwareException>> $caughtExceptions
     * @param list<class-string> $middleware
     */
    public function __construct(
        public string  $type,
        public string  $fullyQualifiedClassName,
        public string  $methodName,
        public string  $name,
        public string  $namespace,
        public ?string $inputParameterName,
        public ?string $description,
        public array   $caughtExceptions,
        public array   $middleware,
    )
    {
    }

    public function fullyQualifiedName(): string
    {
        return "{$this->namespace}.{$this->name}";
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $type = PHPExport::export($this->type);
        $fullyQualifiedClassName = PHPExport::export($this->fullyQualifiedClassName);
        $methodName = PHPExport::export($this->methodName);
        $namespace = PHPExport::export($this->namespace);
        $inputParameterName = PHPExport::export($this->inputParameterName);
        $caughtExceptions = PHPExport::exportArray($this->caughtExceptions);
        $name = PHPExport::export($this->name);
        $middleware = PHPExport::exportArray($this->middleware);

        // Descriptions are ignored when caching.
        return "new {$className}({$type}, {$fullyQualifiedClassName}, {$methodName},  {$name}, {$namespace}, {$inputParameterName}, null, {$caughtExceptions}, {$middleware})";
    }
}