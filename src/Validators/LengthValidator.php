<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Validators;

use Attribute;
use Le0daniel\PhpTsBindings\Contracts\Constraint;
use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final readonly class LengthValidator implements Constraint
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

    public function validate(mixed $value, ExecutionContext $context): bool
    {
        $valueToValidate = match (gettype($value)) {
            'array' => count($value),
            'string' => strlen($value),
            'integer' => $value,
            default => null,
        };

        if (is_null($valueToValidate)) {
            $context->addIssue(new Issue(
                \Le0daniel\PhpTsBindings\Executor\Data\IssueMessage::INVALID_TYPE,
                [
                    'message' => "Wrong type for length validation. Expected string, array or integer, got: " . gettype($value),
                    'value' => $value,
                ],
            ));
            return false;
        }

        if (!$this->validateMin($valueToValidate)) {
            $context->addIssue(new Issue(
                'validation.invalid_min',
                [
                    'message' => "Expected value to be at least {$this->min} characters long, got: {$valueToValidate}.",
                    'including' => $this->including,
                    'type' => gettype($value),
                    'value' => $value,
                ],
            ));
            return false;
        }

        if (!$this->validateMax($valueToValidate)) {
            $context->addIssue(new Issue(
                'validation.invalid_max',
                [
                    'message' => "Expected value to be at most {$this->max} characters long, got: {$valueToValidate}.",
                    'including' => $this->including,
                    'type' => gettype($value),
                    'value' => $value,
                ],
            ));
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