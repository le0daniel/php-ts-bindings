<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Data;

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
    private const string LOCAL_TYPE_REGEX = "/@phpstan-type\s+(?<typeName>{cn})\s+(?<typeDefinition>.+)/";
    private const string IMPORTED_TYPE_REGEX = '/@phpstan-import-type\s+(?<typeName>{cn})\s+from\s+(?<fromClass>{fqcn})(\s+as\s+(?<alias>{cn}))?/';

    /**
     * @param string|null $namespace
     * @param array<string, class-string> $usedNamespaceMap
     * @param array<string, string> $localTypes
     * @param array<string, ImportedType> $importedTypes
     */
    public function __construct(
        public ?string $namespace = null,
        public array   $usedNamespaceMap = [],
        public array   $localTypes = [],
        public array   $importedTypes = [],
    )
    {
    }

    public function toFullyQualifiedClassName(string $className): string
    {
        return Utils\Namespaces::toFullyQualifiedClassName($className, $this->namespace, $this->usedNamespaceMap);
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

    public static function fromClassReflection(ReflectionClass $class): self
    {
        $reflector = new FileReflector($class->getFileName());
        $namespace = $reflector->getNamespace();
        $useNamespaceMap = Utils\Namespaces::buildNamespaceAliasMap($reflector->getUsedNamespaces());

        return new self(
            $namespace,
            $useNamespaceMap,
            self::findLocallyDefinedTypes($class->getDocComment()),
            self::findImportedTypeDefinition($class->getDocComment(), $namespace, $useNamespaceMap),
        );
    }

    /**
     * @throws ReflectionException
     */
    public static function fromFilePath(string $filePath): self
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

    private static function findLocallyDefinedTypes(null|false|string $docBlock): array
    {
        if (empty($docBlock)) {
            return [];
        }

        $localTypes = [];
        $lines = explode(PHP_EOL, $docBlock);
        foreach ($lines as $line) {
            $matches = [];
            if (preg_match(self::compileRegex(self::LOCAL_TYPE_REGEX), $line, $matches) === 1) {
                $localTypes[$matches['typeName']] = trim($matches['typeDefinition']);
            }
        }
        return $localTypes;
    }
}