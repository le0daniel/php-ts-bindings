<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Data;

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
    )
    {
    }

    /**
     * @return string
     */
    public function getInputSchemaName(): string
    {
        return "{$this->type}@{$this->fullyQualifiedName()}#input";
    }

    /**
     * Those methods are used to create the output schema name when generating types and asts.
     * @return string
     */
    public function getOutputSchemaName(): string
    {
        return "{$this->type}@{$this->fullyQualifiedName()}#output";
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

        // Descriptions are ignored when caching.
        return "new {$className}({$type}, {$fullyQualifiedClassName}, {$methodName}, {$namespace}, {$inputParameterName}, null, {$caughtExceptions})";
    }
}