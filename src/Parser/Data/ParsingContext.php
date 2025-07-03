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
    private const array REGEX_PARTS = [
        '{cn}' => '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*',
        '{fqcn}' => '\\\\?[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*(\\\\[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)*',
    ];
    private const string LOCAL_TYPE_REGEX = "/@phpstan-type\s+(?<typeName>[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\s+(?<typeDefinition>[^@]+)/m";
    private const string IMPORTED_TYPE_REGEX = '/@phpstan-import-type\s+(?<typeName>{cn})\s+from\s+(?<fromClass>{fqcn})(\s+as\s+(?<alias>{cn}))?/';
    private const string GENERICS_TYPE_REGEX = '/@template\s+(?<genericName>{cn})/';

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

    private static function compileRegex(string $regex): string
    {
        return str_replace(array_keys(self::REGEX_PARTS), array_values(self::REGEX_PARTS), $regex);
    }

    public static function regex(): string
    {
        return self::compileRegex(self::IMPORTED_TYPE_REGEX);
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

    /**
     * @param list<NodeInterface> $generics
     * @throws ReflectionException
     */
    public static function fromClassString(string $classString, array $generics = []): self
    {
        return self::fromClassReflection(new ReflectionClass($classString), $generics);
    }

    /**
     * @param ReflectionClass<object> $class
     * @param list<NodeInterface> $generics
     * @return self
     */
    public static function fromClassReflection(ReflectionClass $class, array $generics = []): self
    {
        $reflector = new FileReflector($class->getFileName());
        $namespace = $reflector->getNamespace();
        $useNamespaceMap = Utils\Namespaces::buildNamespaceAliasMap($reflector->getUsedNamespaces());

        return new self(
            $namespace,
            $useNamespaceMap,
            self::findLocallyDefinedTypes($class->getDocComment()),
            self::findImportedTypeDefinition($class->getDocComment(), $namespace, $useNamespaceMap),
            self::assignGenerics($class->getDocComment(), $generics),
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
            self::findLocallyDefinedTypes($class->getDocComment()),
            self::findImportedTypeDefinition($class->getDocComment(), $namespace, $useNamespaceMap),
            self::assignGenerics($class->getDocComment(), $generics),
        );
    }

    /**
     * @param false|string|null $docBlock
     * @param string|null $namespace
     * @param array<string, class-string> $usedNamespaces
     * @return array<string,ImportedType>
     */
    private static function findImportedTypeDefinition(null|false|string $docBlock, ?string $namespace, array $usedNamespaces): array
    {
        if (empty($docBlock)) {
            return [];
        }

        $importedTypes = [];
        foreach (explode(PHP_EOL, $docBlock) as $line) {
            $matches = [];
            if (preg_match(self::compileRegex(self::IMPORTED_TYPE_REGEX), $line, $matches) !== 1) {
                continue;
            }

            $importedTypeName = $matches['typeName'];
            $fromClass = Utils\Namespaces::toFullyQualifiedClassName($matches['fromClass'], $namespace, $usedNamespaces);
            $localAlias = $matches['alias'] ?? null;
            $importedTypes[$localAlias ?? $importedTypeName] = [
                'className' => $fromClass,
                'typeName' => $importedTypeName,
            ];
        }
        return $importedTypes;
    }

    /**
     * @param NodeInterface[] $generics
     * @return array<string, NodeInterface>
     */
    private static function assignGenerics(null|false|string $docBlock, array $generics): array
    {
        $declaredGenerics = self::findGenerics($docBlock);
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

    /**
     * @return list<string>
     */
    private static function findGenerics(null|false|string $docBlock): array
    {
        if (empty($docBlock)) {
            return [];
        }

        $matches = [];
        $result = preg_match_all(
            self::compileRegex(self::GENERICS_TYPE_REGEX),
            Utils\PhpDoc::normalize($docBlock),
            $matches,
            PREG_SET_ORDER
        );

        if (!$result) {
            return [];
        }

        return array_map(fn(array $match): string => $match['genericName'], $matches);
    }

    /** @return array<string,string> */
    private static function findLocallyDefinedTypes(null|false|string $docBlock): array
    {
        if (empty($docBlock)) {
            return [];
        }

        $matches = [];
        $result = preg_match_all(
            self::compileRegex(self::LOCAL_TYPE_REGEX),
            Utils\PhpDoc::normalize($docBlock),
            $matches,
            PREG_SET_ORDER
        );

        if (!$result) {
            return [];
        }

        $localTypes = [];
        foreach ($matches as $match) {
            $localTypes[$match['typeName']] = trim(str_replace(PHP_EOL, ' ', $match['typeDefinition']));
        }

        return $localTypes;
    }
}