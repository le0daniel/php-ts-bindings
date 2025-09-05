<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Data;

final class OperationCode
{
    /**
     * Imports should specify types to be imported from other code.
     * Especially if you define helper functions, you can use this to define
     * specific code to construct your function.
     *
     * @param string $content
     * @param list<ImportStatement>|null $imports
     */
    public function __construct(
        public string $content,
        public ?array $imports = null,
    )
    {
    }
}