<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Casters;

use Le0daniel\PhpTsBindings\Contracts\CastsStruct;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final readonly class CustomObjectCaster implements CastsStruct
{
    /**
     * @param class-string $fullyQualifiedClassName
     * @param ObjectCastStrategy $strategy
     */
    public function __construct(
        private string $fullyQualifiedClassName,
        private ObjectCastStrategy $strategy,
    )
    {
    }

    /**
     * @param array<string, mixed> $validatedProperties
     */
    public function castToStruct(array $validatedProperties): object
    {
        if ($this->strategy === ObjectCastStrategy::CONSTRUCTOR) {
            return new $this->fullyQualifiedClassName(...$validatedProperties);
        }

        $instance = new $this->fullyQualifiedClassName;
        foreach ($validatedProperties as $name => $value) {
            $instance->{$name} = $value;
        }
        return $instance;
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $fullyQualifiedClassName = PHPExport::export($this->fullyQualifiedClassName);
        $strategy = PHPExport::exportEnumCase($this->strategy);
        return "new {$className}({$fullyQualifiedClassName}, {$strategy})";
    }
}