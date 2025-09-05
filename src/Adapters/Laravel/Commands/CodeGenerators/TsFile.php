<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators;

use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\ImportStatement;

final class TsFile
{

    /**
     * @param list<ImportStatement> $imports
     * @param string $content
     */
    public function __construct(
        private(set) array $imports = [],
        private(set) string $content = "",
    )
    {
    }

    public static function fromString(string|TsFile $content): TsFile
    {
        if ($content instanceof TsFile) {
            return $content;
        }
        return new TsFile(imports: [], content: $content);
    }

    public function addImports(ImportStatement ...$imports): void
    {
        foreach ($imports as $import) {
            if (array_key_exists($import->from, $this->imports)) {
                $this->imports[$import->from] = $this->imports[$import->from]->merge($import);
                continue;
            }

            $this->imports[$import->from] = $import;
        }
    }

    public function addContent(string $content): void
    {
        $this->content .= $content;
    }

    public function merge(TsFile $other): void
    {
        $this->addImports(...$other->imports);
        $this->addContent($other->content);
    }

    public function toString(): string
    {
        $imports = implode(PHP_EOL, array_map(fn(ImportStatement $import): string => $import->toString(), $this->imports));
        $fullFile = <<<TypeScript
{$imports}

{$this->content}
TypeScript;
        return trim($fullFile) . PHP_EOL;
    }
}