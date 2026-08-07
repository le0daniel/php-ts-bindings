<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Reflection;

use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use ReflectionClass;
use ReflectionException;

final class FileReflector
{
    /** @var list<string|array{int, string, int}>|null */
    private ?array $tokens = null;

    /**
     * Keys and values are whatever the file's `use` statements spell; they are not verified to name
     * anything that exists, so this is not class-string.
     *
     * @var array<int|string, string>|null
     */
    private ?array $usedNamespaces = null;

    private ?string $namespace = null;

    private bool $namespaceParsed = false;

    /**
     * @var ReflectionClass<object>|null
     */
    private ?ReflectionClass $declaredClass = null;

    /**
     * @throws ParserException
     */
    public function __construct(
        public readonly string $filePath
    ) {
        $realPath = realpath($this->filePath);
        if ($realPath === false || ! is_file($realPath) || ! is_readable($realPath)) {
            throw new ParserException(
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

        $tokens = $this->tokens();
        $namespaces = [];
        $numTokens = count($tokens);
        $depth = 0;

        for ($i = 0; $i < $numTokens; $i++) {
            $token = $tokens[$i];

            // A group use's own braces never reach here: parseUseStatement consumes them and
            // returns the index of the closing `;`.
            if ($token === '{') {
                $depth++;

                continue;
            }
            if ($token === '}') {
                $depth--;

                continue;
            }

            if (! is_array($token) || $token[0] !== T_USE) {
                continue;
            }

            // Imports are top level statements. Inside a body, `use` composes a trait.
            if ($depth > 0) {
                continue;
            }

            // Skip `use function` and `use const`. A null means the next token is punctuation,
            // which for a `use` only ever means the `(` of a closure's capture list - not an
            // import, and reading it as one would scan into the closure body.
            $nextToken = self::peekNextSignificantToken($tokens, $i, $numTokens);
            if ($nextToken === null || in_array($nextToken[0], [T_FUNCTION, T_CONST], true)) {
                continue;
            }

            [$imports, $i] = self::parseUseStatement($tokens, $i, $numTokens);

            foreach ($imports as [$fullyQualifiedClassName, $alias]) {
                if ($alias !== null) {
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

        $this->namespace = self::findNamespaceInTokens($this->tokens());
        $this->namespaceParsed = true;

        return $this->namespace;
    }

    /**
     * Lazily finds the first declared class, interface, trait, or enum in the file
     * and returns a ReflectionClass instance for it.
     *
     * @return ReflectionClass<object>|never
     *
     * @throws ParserException If no class-like structure is found or if the class cannot be loaded.
     * @throws ReflectionException If the class is loaded but cannot be reflected.
     */
    public function getDeclaredClass(): ReflectionClass
    {
        if ($this->declaredClass !== null) {
            return $this->declaredClass;
        }

        $namespace = $this->getNamespace();
        $className = self::findClassNameInTokens($this->tokens());

        if ($className === null) {
            throw new ParserException(
                "No class, interface, trait, or enum found in file: {$this->filePath}"
            );
        }

        $fullyQualifiedClassName = $namespace ? "{$namespace}\\{$className}" : $className;

        // This is critical. We must ensure the file is loaded into memory
        // before we can reflect a class from it, especially if not using an autoloader.
        if (! class_exists($fullyQualifiedClassName, false) && ! interface_exists($fullyQualifiedClassName, false) && ! trait_exists($fullyQualifiedClassName, false)) {
            require_once $this->filePath;
        }

        if (! class_exists($fullyQualifiedClassName, false) && ! interface_exists($fullyQualifiedClassName, false) && ! trait_exists($fullyQualifiedClassName, false)) {
            throw new ParserException("Failed to load class {$fullyQualifiedClassName} from file {$this->filePath}");
        }

        return $this->declaredClass = new ReflectionClass($fullyQualifiedClassName);
    }

    /**
     * Returns the tokens rather than only populating $tokens, so callers hold a non-null list and
     * every read below is provably safe instead of relying on having called this first.
     *
     * @return list<string|array{int, string, int}>
     */
    private function tokens(): array
    {
        if ($this->tokens !== null) {
            return $this->tokens;
        }

        $content = file_get_contents($this->filePath);
        if ($content === false) {
            throw new ParserException(
                "Could not read file content: {$this->filePath}"
            );
        }

        return $this->tokens = token_get_all($content);
    }

    /**
     * @param  list<string|array{int, string, int}>  $tokens
     * @return string|null The found namespace name or null.
     */
    private static function findNamespaceInTokens(array $tokens): ?string
    {
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                $nextToken = self::peekNextSignificantToken($tokens, $i, $count);
                if ($nextToken && in_array($nextToken[0], [T_STRING, T_NAME_QUALIFIED], true)) {
                    return $nextToken[1];
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string|array{int, string, int}>  $tokens
     * @return string|null The found class name or null.
     */
    private static function findClassNameInTokens(array $tokens): ?string
    {
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (! is_array($token)) {
                continue;
            }

            if (! in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                continue;
            }

            // `Foo::class` tokenizes as T_CLASS too. Only a T_CLASS that is not preceded by `::`
            // introduces a declaration.
            $previousToken = self::previousSignificantToken($tokens, $i);
            if ($token[0] === T_CLASS && $previousToken !== null && $previousToken[0] === T_DOUBLE_COLON) {
                continue;
            }

            $nextToken = self::peekNextSignificantToken($tokens, $i, $count);
            if ($nextToken && $nextToken[0] === T_STRING) {
                return $nextToken[1];
            }
        }

        return null;
    }

    /**
     * @param  list<string|array{int, string, int}>  $tokens
     * @return (array{int, string, int})|null Null when the preceding token is punctuation, which
     *                                        token_get_all() reports as a plain string rather than an array.
     */
    private static function previousSignificantToken(array $tokens, int $currentIndex): ?array
    {
        for ($i = $currentIndex - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            return is_array($token) ? $token : null;
        }

        return null;
    }

    /**
     * @param  list<string|array{int, string, int}>  $tokens
     * @return (array{int, string, int})|null
     */
    private static function peekNextSignificantToken(array $tokens, int $currentIndex, int $maxIndex): ?array
    {
        for ($i = $currentIndex + 1; $i < $maxIndex; $i++) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            // Punctuation arrives as a plain string, and it always ends the construct being read:
            // `new class {` must not scan on into the body looking for a name.
            return is_array($token) ? $token : null;
        }

        return null;
    }

    /**
     * Reads one `use` statement, from its T_USE token up to the terminating `;`.
     *
     * A single import yields one entry; a group (`use App\Data\{Order, Customer as C};`) yields one
     * per member, each carrying its own alias. The leading name arrives as T_STRING when it has a
     * single segment, T_NAME_QUALIFIED otherwise, and T_NAME_FULLY_QUALIFIED when it was written
     * with a leading backslash - all three have to be read or the import is silently lost.
     *
     * @param  list<string|array{int, string, int}>  $tokens
     * @return array{list<array{string, string|null}>, int} The imports and the index of the `;`.
     */
    private static function parseUseStatement(array $tokens, int $startIndex, int $maxIndex): array
    {
        $imports = [];
        $prefix = '';
        $name = null;
        $alias = null;
        $expectAlias = false;
        $i = $startIndex + 1;

        for (; $i < $maxIndex; $i++) {
            $token = $tokens[$i];

            if ($token === ';') {
                break;
            }

            // Everything read so far is the group's shared prefix; the members follow.
            if ($token === '{') {
                $prefix = $name === null ? '' : "{$name}\\";
                $name = null;
                $alias = null;

                continue;
            }

            if ($token === ',') {
                if ($name !== null) {
                    $imports[] = [$prefix.$name, $alias];
                }
                $name = null;
                $alias = null;
                $expectAlias = false;

                continue;
            }

            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_AS) {
                $expectAlias = true;

                continue;
            }

            if (! in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            if ($expectAlias) {
                $alias = $token[1];
                $expectAlias = false;

                continue;
            }

            $name = $token[1];
        }

        if ($name !== null) {
            $imports[] = [$prefix.$name, $alias];
        }

        return [$imports, $i];
    }
}
