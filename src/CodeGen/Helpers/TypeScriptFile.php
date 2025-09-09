<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Helpers;

use Stringable;

final class TypeScriptFile implements Stringable
{

    /**
     * @param list<TypescriptImportStatement> $imports
     * @param string $code
     */
    public function __construct(
        private(set) array $imports = [],
        private(set) string $code = "",
    )
    {
    }

    public static function from(string|TypeScriptFile $content): TypeScriptFile
    {
        if ($content instanceof TypeScriptFile) {
            return $content;
        }
        return new TypeScriptFile(imports: [], code: $content);
    }

    public function addImports(TypescriptImportStatement ...$imports): void
    {
        foreach ($imports as $import) {
            if (array_key_exists($import->from, $this->imports)) {
                $this->imports[$import->from] = $this->imports[$import->from]->merge($import);
                continue;
            }

            $this->imports[$import->from] = $import;
        }
    }

    public function append(string|TypescriptCodeBlock $code): void
    {
        if ($code instanceof TypescriptCodeBlock) {
            $this->addImports(...$code->imports ?? []);

            // For codeblocks we append a new line at the end.
            $this->code .= $code->code . PHP_EOL;
            return;
        }

        $this->code .= $code;
    }

    public function merge(TypeScriptFile $other): void
    {
        $this->addImports(...$other->imports);
        $this->append($other->code);
    }

    public function toString(): string
    {
        $imports = implode(PHP_EOL, array_map(fn(TypescriptImportStatement $import): string => $import->toString(), $this->imports));
        $fullFile = <<<TypeScript
{$imports}

{$this->code}
TypeScript;
        return trim($fullFile) . PHP_EOL;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}