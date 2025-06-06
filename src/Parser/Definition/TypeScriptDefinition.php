<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Definition;

final readonly class TypeScriptDefinition
{
    public const float DEFAULT_NAMESPACE = 0.78;

    public function __construct(
        public string $inputDefinition,
        public string $outputDefinition,
    )
    {
    }
}