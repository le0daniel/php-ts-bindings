<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Helpers;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Utils\Lists;

final readonly class TypescriptImportStatement
{
    /**
     * @var list<string>
     */
    private array $imports;

    /**
     * @param string $from
     * @param string|list<string> $imports
     */
    public function __construct(
        public string $from,
        string|array  $imports = [],
    )
    {
        $this->imports = is_string($imports) ? [$imports] : $imports;
    }

    public function merge(TypescriptImportStatement $other): TypescriptImportStatement
    {
        if ($this->from !== $other->from) {
            throw new InvalidArgumentException("Cannot merge imports from different files");
        }

        $uniqueImports = array_values(array_unique([
            ... $this->getImports(),
            ... $other->getImports()
        ]));

        return new TypescriptImportStatement($this->from, $uniqueImports);
    }

    /** @return list<string> */
    public function toStatements(): array
    {
        $typeImports = [];
        $valueImports = [];

        foreach ($this->imports as $statement) {
            if (str_starts_with($statement, 'type ')) {
                $typeImports[] = substr($statement, 5);
            } else {
                $valueImports[] = $statement;
            }
        }

        return Lists::filterNullValues([
            $this->toImport(true, $typeImports),
            $this->toImport(false, $valueImports)
        ]);
    }

    /**
     * @param bool $isTypeImport
     * @param list<string> $imports
     * @return string|null
     */
    private function toImport(bool $isTypeImport, array $imports): string|null
    {
        if (empty($imports)) {
            return null;
        }

        usort($imports, fn(string $a, string $b): int => strcmp($a, $b));

        $importedValues = implode(', ', $imports);
        return $isTypeImport ? "import type {{$importedValues}} from '{$this->from}';" : "import {{$importedValues}} from '{$this->from}';";
    }

    /**
     * @return list<string>
     */
    public function getImports(): array
    {
        return array_map(fn(string $import): string => trim($import), $this->imports);
    }
}