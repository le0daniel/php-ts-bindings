<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Helpers;

use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Reflection\FileReflector;
use Le0daniel\PhpTsBindings\Utils;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

/**
 * @phpstan-type ImportedType = array{className: string, typeName: string}
 */
final readonly class ParsingScope
{
    /**
     * Alias => fully qualified name, keyed lowercase. PHP resolves `use` aliases case
     * insensitively, so the keys are normalized here rather than trusted: a hand-written map -
     * this is public API - would otherwise silently miss on the wrong casing.
     *
     * @var array<string, string>
     */
    public array $usedNamespaceMap;

    /**
     * @param  array<string, string>  $usedNamespaceMap
     * @param  array<string, string>  $localTypes
     * @param  array<string, ImportedType>  $importedTypes
     * @param  array<string, NodeInterface>  $generics
     * @param  class-string|null  $declaredInClass
     */
    public function __construct(
        public ?string $namespace = null,
        array $usedNamespaceMap = [],
        public array $localTypes = [],
        public array $importedTypes = [],
        public array $generics = [],
        public ?string $declaredInClass = null,
    ) {
        $this->usedNamespaceMap = array_change_key_case($usedNamespaceMap);
    }

    /**
     * Given an identifier, returns the fully qualified class name without leading backslash.
     */
    public function toFullyQualifiedClassName(string $className): string
    {
        return Utils\Namespaces::toFullyQualifiedClassName($className, $this->namespace, $this->usedNamespaceMap);
    }

    public function isGeneric(string $genericName): bool
    {
        return array_key_exists($genericName, $this->generics);
    }

    public function getGeneric(string $genericName): NodeInterface
    {
        return $this->generics[$genericName];
    }

    public function isLocalType(string $typeName): bool
    {
        return array_key_exists($typeName, $this->localTypes);
    }

    /**
     * @throws ParserException
     */
    public function getLocalTypeDefinition(string $typeName): string
    {
        if (! $this->isLocalType($typeName)) {
            throw new ParserException("Type definition for {$typeName} not found");
        }

        return $this->localTypes[$typeName];
    }

    public function isImportedType(string $typeName): bool
    {
        return array_key_exists($typeName, $this->importedTypes);
    }

    /**
     * @return ImportedType
     */
    public function getImportedTypeInfo(string $typeName): array
    {
        if (! $this->isImportedType($typeName)) {
            throw new ParserException("Type definition for {$typeName} not found");
        }

        return $this->importedTypes[$typeName];
    }

    public function descendIntoDeclaringClass(ReflectionProperty|ReflectionParameter $property): self
    {
        // A ReflectionParameter belonging to a closure has no declaring class, so there is nothing
        // to descend into and the current context is already the right one.
        $declaringClass = $property->getDeclaringClass();
        if ($declaringClass === null || $this->declaredInClass === $declaringClass->getName()) {
            return $this;
        }

        // ToDo: Identify the generics that should be passed down. Currently ignored.
        return self::fromReflectionClass($declaringClass);
    }

    /**
     * A method's PHPDoc is written where the method is, which for an inherited or trait-composed
     * method is not the class it was reached through. The file is taken from the method itself
     * rather than from getDeclaringClass(), because for a trait method that reports the composing
     * class while the `use` statements the PHPDoc relies on live in the trait's file.
     */
    public function descendIntoDeclaringFileOf(ReflectionMethod $method): self
    {
        $fileName = $method->getFileName();
        if ($fileName === false || $fileName === $this->declaringFile()) {
            return $this;
        }

        // ToDo: Identify the generics that should be passed down. Currently ignored.
        return self::fromFilePath($fileName);
    }

    private function declaringFile(): ?string
    {
        if ($this->declaredInClass === null) {
            return null;
        }

        $fileName = new ReflectionClass($this->declaredInClass)->getFileName();

        return $fileName === false ? null : $fileName;
    }

    /**
     * @param  list<NodeInterface>  $generics
     *
     * @throws ReflectionException
     */
    public static function fromClassString(string $classString, array $generics = []): self
    {
        if (! class_exists($classString) && ! interface_exists($classString)) {
            throw new ParserException("Cannot build a parsing context for unknown class {$classString}.");
        }

        return self::fromReflectionClass(new ReflectionClass($classString), $generics);
    }

    /**
     * @param  ReflectionClass<object>  $class
     * @param  list<NodeInterface>  $generics
     */
    public static function fromReflectionClass(ReflectionClass $class, array $generics = []): self
    {
        $fileName = $class->getFileName();
        if ($fileName === false) {
            throw new ParserException(
                "Cannot build a parsing context for {$class->getName()}: it is not defined in a file."
            );
        }

        $reflector = new FileReflector($fileName);
        $namespace = $reflector->getNamespace();
        $useNamespaceMap = Utils\Namespaces::buildNamespaceAliasMap($reflector->getUsedNamespaces());

        return new self(
            $namespace,
            $useNamespaceMap,
            Utils\PhpDoc::findLocallyDefinedTypes($class->getDocComment()),
            self::findFullyQualifiedImportedTypes($class->getDocComment(), $namespace, $useNamespaceMap),
            self::assignGenerics($class->getDocComment(), $generics),
            $class->getName(),
        );
    }

    /**
     * @param  array<string, NodeInterface>  $generics
     *
     * @throws ReflectionException
     */
    public static function fromFilePath(string $filePath, array $generics = []): self
    {
        $reflector = new FileReflector($filePath);
        $class = $reflector->getDeclaredClass();
        $namespace = $reflector->getNamespace();
        $useNamespaceMap = Utils\Namespaces::buildNamespaceAliasMap($reflector->getUsedNamespaces());

        return new self(
            $reflector->getNamespace(),
            $useNamespaceMap,
            Utils\PhpDoc::findLocallyDefinedTypes($class->getDocComment()),
            self::findFullyQualifiedImportedTypes($class->getDocComment(), $namespace, $useNamespaceMap),
            self::assignGenerics($class->getDocComment(), $generics),
            $class->getName(),
        );
    }

    /**
     * @param  array<string, string>  $usedNamespaces
     * @return array<string,ImportedType>
     */
    private static function findFullyQualifiedImportedTypes(null|false|string $docBlock, ?string $namespace, array $usedNamespaces): array
    {
        return array_map(fn (array $import) => [
            'typeName' => $import['typeName'],
            'className' => Utils\Namespaces::toFullyQualifiedClassName($import['className'], $namespace, $usedNamespaces),
        ], Utils\PhpDoc::findImportedTypeDefinition($docBlock));
    }

    /**
     * @param  NodeInterface[]  $generics
     * @return array<string, NodeInterface>
     */
    private static function assignGenerics(null|false|string $docBlock, array $generics): array
    {
        $declaredGenerics = Utils\PhpDoc::findGenerics($docBlock);
        if (count($declaredGenerics) !== count($generics)) {
            $declaredGenericNames = implode(', ', $declaredGenerics);
            $expectedCount = count($declaredGenerics);
            $actualCount = count($generics);

            throw new ParserException("Number of generics does not match. Expected {$expectedCount} <{$declaredGenericNames}>, got {$actualCount}.");
        }

        $assignedGenerics = [];
        foreach ($declaredGenerics as $index => $genericName) {
            $assignedGenerics[$genericName] = $generics[$index];
        }

        return $assignedGenerics;
    }
}
