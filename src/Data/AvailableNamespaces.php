<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Data;

use Le0daniel\PhpTsBindings\Reflection\FileReflector;
use Le0daniel\PhpTsBindings\Utils\Namespaces;
use ReflectionClass;

final readonly class AvailableNamespaces
{
    public function __construct(
        private null|string $namespace = null,
        private array $usedNamespaceMap = [],
    )
    {
    }

    public static function fromReflectionClass(ReflectionClass $reflectionClass): self
    {
        $reflection = new FileReflector($reflectionClass->getFileName());
        return new self(
            $reflection->getNamespace(),
            Namespaces::buildNamespaceAliasMap(
               $reflection->getUsedNamespaces()
            )
        );
    }

    public function isEmpty(): bool
    {
        return $this->namespace === null && empty($this->usedNamespaceMap);
    }

    public function applyTo(string $value): string
    {
        if (str_starts_with($value, '\\')) {
            return $value;
        }

        if (array_key_exists($value, $this->usedNamespaceMap)) {
            return $this->usedNamespaceMap[$value];
        }

        if ($this->namespace === null) {
            return $value;
        }

        return $this->namespace . '\\' . $value;
    }
}