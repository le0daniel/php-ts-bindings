<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Data;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Reflection\FileReflector;
use Le0daniel\PhpTsBindings\Utils;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * @phpstan-type ImportedType = array{className: string, typeName: string}
 */
final readonly class ParsingContext
{
    /**
     * @param string|null $namespace
     * @param array<string, class-string> $usedNamespaceMap
     * @param array<string, string> $localTypes
     * @param array<string, ImportedType> $importedTypes
     * @param array<string, NodeInterface> $generics
     */
    public function __construct(
        public ?string $namespace = null,
        public array   $usedNamespaceMap = [],
        public array   $localTypes = [],
        public array   $importedTypes = [],
        public array   $generics = [],
        public ?string $declaredInClass = null,
    )
    {
    }

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
     * @throws RuntimeException
     */
    public function getLocalTypeDefinition(string $typeName): string
    {
        if (!$this->isLocalType($typeName)) {
            throw new RuntimeException("Type definition for {$typeName} not found");
        }

        return $this->localTypes[$typeName];
    }

    public function isImportedType(string $typeName): bool
    {
        return array_key_exists($typeName, $this->importedTypes);
    }

    /**
     * @param string $typeName
     * @return ImportedType
     */
    public function getImportedTypeInfo(string $typeName): array
    {
        if (!$this->isImportedType($typeName)) {
            throw new RuntimeException("Type definition for {$typeName} not found");
        }

        return $this->importedTypes[$typeName];
    }

    public function descendIntoDeclaringClass(\ReflectionProperty|\ReflectionParameter $property): self
    {
        // Declaration is in the same class file.
        if ($this->declaredInClass === $property->getDeclaringClass()->getName()) {
            return $this;
        }

        // ToDo: Identify the generics that should be passed down. Currently ignored.
        return self::fromReflectionClass($property->getDeclaringClass());
    }

    /**
     * @param list<NodeInterface> $generics
     * @throws ReflectionException
     */
    public static function fromClassString(string $classString, array $generics = []): self
    {
        return self::fromReflectionClass(new ReflectionClass($classString), $generics);
    }

    /**
     * @param ReflectionClass<object> $class
     * @param list<NodeInterface> $generics
     * @return self
     */
    public static function fromReflectionClass(ReflectionClass $class, array $generics = []): self
    {
        $reflector = new FileReflector($class->getFileName());
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
     * @param array<string, NodeInterface> $generics
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
     * @param false|string|null $docBlock
     * @param string|null $namespace
     * @param array<string, class-string> $usedNamespaces
     * @return array<string,ImportedType>
     */
    private static function findFullyQualifiedImportedTypes(null|false|string $docBlock, ?string $namespace, array $usedNamespaces): array
    {
        return array_map(fn(array $import) => [
            'typeName' => $import['typeName'],
            'className' => Utils\Namespaces::toFullyQualifiedClassName($import['className'], $namespace, $usedNamespaces),
        ], Utils\PhpDoc::findImportedTypeDefinition($docBlock));
    }

    /**
     * @param NodeInterface[] $generics
     * @return array<string, NodeInterface>
     */
    private static function assignGenerics(null|false|string $docBlock, array $generics): array
    {
        $declaredGenerics = Utils\PhpDoc::findGenerics($docBlock);
        if (count($declaredGenerics) !== count($generics)) {
            $declaredGenericNames = implode(', ', $declaredGenerics);
            $expectedCount = count($declaredGenerics);
            $actualCount = count($generics);

            throw new RuntimeException("Number of generics does not match. Expected {$expectedCount} <{$declaredGenericNames}>, got {$actualCount}.");
        }

        $assignedGenerics = [];
        foreach ($declaredGenerics as $index => $genericName) {
            $assignedGenerics[$genericName] = $generics[$index];
        }
        return $assignedGenerics;
    }
}