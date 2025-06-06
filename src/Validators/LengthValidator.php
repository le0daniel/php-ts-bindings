<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Validators;

use Attribute;
use Le0daniel\PhpTsBindings\Contracts\ExportableToPhpCode;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class LengthValidator implements Constraint, ExportableToPhpCode
{
    public function __construct(
        public int|null $min = null,
        public int|null $max = null,
        public bool $including = true,
    )
    {
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $min = PHPExport::export($this->min);
        $max = PHPExport::export($this->max);
        $including = PHPExport::export($this->including);
        return "new {$className}({$min}, {$max}, {$including})";
    }

    public function validate(mixed $value, $context): bool
    {
        $value = match (gettype($value)) {
            'array' => count($value),
            'string' => strlen($value),
            'integer' => $value,
            default => null,
        };

        if (is_null($value)) {
            // Add Error
            return false;
        }

        if (!$this->validateMin($value)) {
            // Add Error
            return false;
        }

        if (!$this->validateMax($value)) {
            // Add Error
            return false;
        }

        return true;
    }

    private function validateMin(int $value): bool
    {
        if (!isset($this->min)) {
            return true;
        }
        return (
            $this->including
                ? $value >= $this->min
                : $value > $this->min
        );
    }

    private function validateMax(int $value): bool
    {
        if (!isset($this->max)) {
            return true;
        }

        return (
            $this->including
                ? $value <= $this->max
                : $value < $this->max
        );
    }
}