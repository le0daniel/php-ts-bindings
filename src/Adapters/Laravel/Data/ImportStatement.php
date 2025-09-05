<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Data;

use InvalidArgumentException;

final readonly class ImportStatement
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

    public function merge(ImportStatement $other): ImportStatement
    {
        if ($this->from !== $other->from) {
            throw new InvalidArgumentException("Cannot merge imports from different files");
        }

        $uniqueImports = array_values(array_unique([
            ... $this->getImports(),
            ... $other->getImports()
        ]));

        return new ImportStatement($this->from, $uniqueImports);
    }

    public function toString(): string
    {
        $imports = $this->getImports();
        usort($imports, fn(string $a, string $b): int => strcmp($a, $b));

        $importedValues = implode(', ', $imports);
        return "import {{$importedValues}} from '{$this->from}';";
    }

    /**
     * @return list<string>
     */
    public function getImports(): array
    {
        return array_map(fn(string $import): string => trim($import), $this->imports);
    }
}