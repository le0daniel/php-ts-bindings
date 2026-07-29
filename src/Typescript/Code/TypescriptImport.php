<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Typescript\Code;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\InvalidStringLiteralException;
use Le0daniel\PhpTsBindings\Typescript\Utils\Syntax;
use Le0daniel\PhpTsBindings\Utils\Lists;

/**
 * What one module contributes to a file: the names taken for their runtime value and the names
 * taken for their type only.
 *
 * An instance is always canonical — names are validated, deduplicated and sorted on construction,
 * and a name imported as a value never also appears as a type. TypeScript rejects that pair
 * (TS2440), and it is redundant anyway: importing a class or enum as a value already brings its
 * type meaning along.
 *
 * Rendering is not done here. How the two buckets become `import type {...}` and `import {...}`
 * lines, and in what order modules appear, is TypescriptFile's business.
 *
 * Not modelled, deliberately: aliasing (`Foo as Bar`), default imports, namespace imports
 * (`* as ns`), side effect imports and re-exports. Each would be an additional bucket here plus a
 * line in TypescriptFile. Aliasing in particular needs first class modelling to deduplicate
 * honestly — `Foo as A` and `Foo as B` are two bindings of one export and both must survive, while
 * `Foo as A` and `Bar as A` collide and must not — which a list of names cannot express.
 */
final readonly class TypescriptImport
{
    /**
     * @var list<string> Sorted, unique.
     */
    public array $values;

    /**
     * @var list<string> Sorted, unique, disjoint from $values.
     */
    public array $types;

    /**
     * Prefer values()/types(); the constructor is for the rare module that gives both.
     *
     * @param list<string> $values
     * @param list<string> $types
     * @throws InvalidArgumentException When $from cannot be written as a module specifier.
     * @throws InvalidStringLiteralException When a name is not a valid TypeScript identifier.
     */
    public function __construct(
        public string $from,
        array         $values = [],
        array         $types = [],
    )
    {
        self::assertUsableSpecifier($from);

        $this->values = self::canonical($values, $from);
        $this->types = array_diff(self::canonical($types, $from), $this->values) |> array_values(...);
    }

    /**
     * @param string|list<string> $names
     */
    public static function values(string $from, string|array $names): self
    {
        return new self($from, values: is_string($names) ? [$names] : $names);
    }

    /**
     * @param string|list<string> $names
     */
    public static function types(string $from, string|array $names): self
    {
        return new self($from, types: is_string($names) ? [$names] : $names);
    }

    /**
     * @param string|list<string> $valuesOrNames
     */
    public static function mixed(string $from, string|array $valuesOrNames): self
    {
        $valuesOrNames = is_array($valuesOrNames) ? $valuesOrNames : [$valuesOrNames];
        $values = [];
        $types = [];

        foreach ($valuesOrNames as $name) {
            $trimmed = trim($name);
            if (str_starts_with($trimmed, 'type ')) {
                $types[] = substr($trimmed, 5);
            } else {
                $values[] = $trimmed;
            }
        }

        return new self($from, values: $values, types: $types);
    }

    /**
     * @throws InvalidArgumentException When the two imports name different modules.
     */
    public function merge(self $other): self
    {
        if ($this->from !== $other->from) {
            throw new InvalidArgumentException(
                "Cannot merge imports of '{$this->from}' and '{$other->from}': they are different modules."
            );
        }

        // The constructor re-canonicalizes, so merging can neither duplicate a name nor leave one
        // in both buckets.
        return new self(
            $this->from,
            [...$this->values, ...$other->values],
            [...$this->types, ...$other->types],
        );
    }

    public function isEmpty(): bool
    {
        return $this->values === [] && $this->types === [];
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    private static function canonical(array $names, string $from): array
    {
        foreach ($names as $name) {
            if (!Syntax::isValidIdentifier($name)) {
                throw InvalidStringLiteralException::notAValidTypescriptIdentifier(
                    $name,
                    "imported from '{$from}'",
                );
            }
        }

        return $names |> Lists::unique(...) |> Lists::sorted(...);
    }

    private static function assertUsableSpecifier(string $from): void
    {
        // The specifier is written verbatim inside a single quoted string literal. Whitespace,
        // quotes and backslashes would either break the literal or silently name a module that
        // does not exist, so reject them rather than escape them into something plausible.
        if ($from === '' || preg_match('/[\s\'"\\\\]/', $from) === 1) {
            throw new InvalidArgumentException(
                "'{$from}' cannot be written as a TypeScript module specifier."
            );
        }
    }
}
