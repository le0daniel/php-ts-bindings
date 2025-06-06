<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

/**
 * @template T
 */
final readonly class DefinedObjectNode implements NodeInterface
{
    /**
     * @param class-string<T> $className
     * @param array<string, NodeInterface> $inputProperties
     * @param array<string, NodeInterface> $outputProperties
     */
    public function __construct(
        private string $className,
        private array  $inputProperties,
        private array  $outputProperties,
    )
    {
    }

    public function __toString(): string
    {
        return "object<{$this->className}>";
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $customClassNameLiteral = PhpExport::absolute($this->className);

        $input = implode(',', Arrays::mapWithKeys(
            $this->inputProperties,
            fn(string $name, NodeInterface $node): string => PHPExport::export($name) . '=>' . $node->exportPhpCode(),
        ));

        $output = implode(',', Arrays::mapWithKeys(
            $this->outputProperties,
            fn(string $name, NodeInterface $node): string => PHPExport::export($name) . '=>' . $node->exportPhpCode(),
        ));

        return "new {$className}({$customClassNameLiteral}::class, [{$input}], [{$output}])";
    }
}