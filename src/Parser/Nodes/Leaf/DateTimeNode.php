<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use DateTimeImmutable;
use DateTimeInterface;
use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Throwable;

final readonly class DateTimeNode implements NodeInterface, LeafNode
{
    /**
     * @param class-string<DateTimeInterface> $dateTimeClass
     * @param string $format
     */
    public function __construct(
        public string $dateTimeClass,
        public string $format = DateTimeInterface::ATOM,
    )
    {
    }

    public function __toString(): string
    {
        return PhpExport::absolute($this->dateTimeClass);
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $dateTimeClass = PHPExport::absolute($this->dateTimeClass);
        $format = $this->format === DateTimeInterface::ATOM
            ? ''
            : ',' . PHPExport::export($this->format);
        return "new {$className}({$dateTimeClass}::class{$format})";
    }

    public function parseValue(mixed $value, $context): DateTimeInterface|Value
    {
        if (!is_string($value)) {
            return Value::INVALID;
        }

        try {
            // @phpstan-ignore-next-line
            return $this->dateTimeClass::createFromInterface(
                DateTimeImmutable::createFromFormat($this->format, $value)
            );
        } catch (Throwable $exception) {
            return Value::INVALID;
        }
    }

    /**
     * @return string|Value::INVALID
     */
    public function serializeValue(mixed $value, $context): string|Value
    {
        if (!$value instanceof DateTimeInterface) {
            return Value::INVALID;
        }
        return $value->format($this->format);
    }

    public function inputDefinition(): string
    {
        return "string";
    }

    public function outputDefinition(): string
    {
        return "string";
    }
}