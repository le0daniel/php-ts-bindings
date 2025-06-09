<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Reflection;

use InvalidArgumentException;

final class FileReflector
{
    /** @var list<string|array{int, string, int}>|null */
    private ?array $tokens = null;

    private ?array $usedNamespaces = null;
    private ?string $namespace = null;
    private bool $namespaceParsed = false;
    private ?\ReflectionClass $declaredClass = null;

    /**
     * @param string $filePath
     * @throws InvalidArgumentException
     */
    public function __construct(
        public readonly string $filePath
    ) {
        $realPath = realpath($this->filePath);
        if ($realPath === false || !is_file($realPath) || !is_readable($realPath)) {
            throw new InvalidArgumentException(
                "File does not exist or is not readable: {$this->filePath}"
            );
        }
    }

    /**
     * Lazily parses and returns the file's `use` statements.
     *
     * The output format is a mixed array:
     * - For `use Namespace\Class;`, the value is 'Namespace\Class'.
     * - For `use Namespace\Class as Alias;`, the key is 'Namespace\Class' and the value is 'Alias'.
     *
     * @return array<int|string, string>
     *
     * Example: ["MyClass\ClassName", "App\Models\User" => "BaseUser"]
     */
    public function getUsedNamespaces(): array
    {
        if ($this->usedNamespaces !== null) {
            return $this->usedNamespaces;
        }

        $this->ensureTokensAreParsed();
        $namespaces = [];
        $numTokens = count($this->tokens);

        for ($i = 0; $i < $numTokens; $i++) {
            $token = $this->tokens[$i];

            if (!is_array($token) || $token[0] !== T_USE) {
                continue;
            }

            // Skip `use function` and `use const`
            $nextToken = $this->peekNextSignificantToken($i, $numTokens);
            if ($nextToken && in_array($nextToken[0], [T_FUNCTION, T_CONST], true)) {
                continue;
            }

            [$fullyQualifiedClassName, $alias, $i] = $this->parseUseStatement($i, $numTokens);

            if ($fullyQualifiedClassName) {
                if ($alias) {
                    $namespaces[$fullyQualifiedClassName] = $alias;
                } else {
                    $namespaces[] = $fullyQualifiedClassName;
                }
            }
        }

        return $this->usedNamespaces = $namespaces;
    }

    /**
     * Lazily finds and returns the namespace declared in the file.
     *
     * @return string|null The declared namespace (e.g., "App\Models"), or null if none is found.
     */
    public function getNamespace(): ?string
    {
        if ($this->namespaceParsed) {
            return $this->namespace;
        }

        $this->ensureTokensAreParsed();
        $this->namespace = $this->findNamespaceInTokens();
        $this->namespaceParsed = true;

        return $this->namespace;
    }

    /**
     * Lazily finds the first declared class, interface, trait, or enum in the file
     * and returns a ReflectionClass instance for it.
     *
     * @return \ReflectionClass|never
     * @throws \RuntimeException If no class-like structure is found or if the class cannot be loaded.
     * @throws \ReflectionException If the class is loaded but cannot be reflected.
     */
    public function getDeclaredClass(): \ReflectionClass
    {
        if ($this->declaredClass !== null) {
            return $this->declaredClass;
        }

        $this->ensureTokensAreParsed();

        $namespace = $this->getNamespace();
        $className = $this->findClassNameInTokens();

        if ($className === null) {
            throw new \RuntimeException(
                "No class, interface, trait, or enum found in file: {$this->filePath}"
            );
        }

        $fullyQualifiedClassName = $namespace ? "{$namespace}\\{$className}" : $className;

        // This is critical. We must ensure the file is loaded into memory
        // before we can reflect a class from it, especially if not using an autoloader.
        if (!class_exists($fullyQualifiedClassName, false) && !interface_exists($fullyQualifiedClassName, false) && !trait_exists($fullyQualifiedClassName, false)) {
            require_once $this->filePath;
        }

        if (!class_exists($fullyQualifiedClassName, false) && !interface_exists($fullyQualifiedClassName, false) && !trait_exists($fullyQualifiedClassName, false)) {
            throw new \RuntimeException("Failed to load class {$fullyQualifiedClassName} from file {$this->filePath}");
        }

        return $this->declaredClass = new \ReflectionClass($fullyQualifiedClassName);
    }

    private function ensureTokensAreParsed(): void
    {
        if ($this->tokens === null) {
            $content = file_get_contents($this->filePath);
            if ($content === false) {
                throw new \RuntimeException(
                    "Could not read file content: {$this->filePath}"
                );
            }
            $this->tokens = token_get_all($content);
        }
    }

    /**
     * @return string|null The found namespace name or null.
     */
    private function findNamespaceInTokens(): ?string
    {
        $count = count($this->tokens);
        for ($i = 0; $i < $count; $i++) {
            if ($this->tokens[$i][0] === T_NAMESPACE) {
                $nextToken = $this->peekNextSignificantToken($i, $count);
                if ($nextToken && in_array($nextToken[0], [T_STRING, T_NAME_QUALIFIED], true)) {
                    return $nextToken[1];
                }
            }
        }
        return null;
    }

    /**
     * @return string|null The found class name or null.
     */
    private function findClassNameInTokens(): ?string
    {
        $count = count($this->tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $this->tokens[$i];
            if (!is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])) {
                $nextToken = $this->peekNextSignificantToken($i, $count);
                if ($nextToken && $nextToken[0] === T_STRING) {
                    return $nextToken[1];
                }
            }
        }
        return null;
    }

    /**
     * @param int $currentIndex
     * @param int $maxIndex
     * @return (array{int, string, int})|null
     */
    private function peekNextSignificantToken(int $currentIndex, int $maxIndex): ?array
    {
        for ($i = $currentIndex + 1; $i < $maxIndex; $i++) {
            $token = $this->tokens[$i];
            if (is_array($token) && $token[0] !== T_WHITESPACE) {
                return $token;
            }
        }
        return null;
    }

    /**
     * @param int $startIndex
     * @param int $maxIndex
     * @return array{string, string|null, int}
     */
    private function parseUseStatement(int $startIndex, int $maxIndex): array
    {
        $fullyQualifiedClassname = '';
        $alias = null;
        $i = $startIndex + 1;

        while ($i < $maxIndex) {
            $token = $this->tokens[$i];
            if ($token === ';') {
                break;
            }

            if (is_array($token)) {
                switch ($token[0]) {
                    case T_NAME_QUALIFIED:
                        $fullyQualifiedClassname = $token[1];
                        break;
                    case T_AS:
                        $aliasToken = $this->peekNextSignificantToken($i, $maxIndex);
                        if ($aliasToken && $aliasToken[0] === T_STRING) {
                            $alias = $aliasToken[1];
                        }
                        break;
                }
            }
            $i++;
        }

        return [$fullyQualifiedClassname, $alias, $i];
    }
}