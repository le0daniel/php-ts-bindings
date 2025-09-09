<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Helpers;

final class TypescriptCodeBlock
{
    /**
     * Imports should specify types to be imported from other code.
     * Especially if you define helper functions, you can use this to define
     * specific code to construct your function.
     *
     * @param string $code
     * @param list<TypescriptImportStatement>|null $imports
     */
    public function __construct(
        public string $code = '',
        public ?array $imports = null,
    )
    {
    }

    public function append(string $code): self
    {
        $this->code .= $code;
        return $this;
    }

    public function addImport(TypescriptImportStatement $import): self
    {
        $this->imports ??= [];
        $this->imports[] = $import;
        return $this;
    }
}