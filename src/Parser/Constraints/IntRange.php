<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Constraints;

use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Parser\Contracts\Constraint;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

/**
 * Backs `int<min, max>`, `positive-int`, `negative-int`, `non-negative-int` and
 * `non-positive-int`.
 *
 * PHPStan ranges are inclusive at both ends, so there is no exclusive variant to configure. An
 * open end is null rather than PHP_INT_MIN/PHP_INT_MAX: `int<min, 100>` states that there is no
 * lower bound, which is not the same claim as "the lower bound happens to be the smallest int
 * this platform can hold".
 */
final readonly class IntRange implements Constraint
{
    public function __construct(
        public ?int $min = null,
        public ?int $max = null,
    )
    {
    }

    #[Override]
    public function validate(mixed $value, ExecutionContext $context): bool
    {
        if (!is_int($value)) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_TYPE,
                [
                    'message' => "Expected int, got: " . gettype($value),
                ],
            ));
            return false;
        }

        if ($this->min !== null && $value < $this->min) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_MIN,
                [
                    'message' => "Expected an int of at least {$this->min}, got: {$value}.",
                    'min' => $this->min,
                    'value' => $value,
                ],
            ));
            return false;
        }

        if ($this->max !== null && $value > $this->max) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_MAX,
                [
                    'message' => "Expected an int of at most {$this->max}, got: {$value}.",
                    'max' => $this->max,
                    'value' => $value,
                ],
            ));
            return false;
        }

        return true;
    }

    #[Override]
    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $min = PHPExport::export($this->min);
        $max = PHPExport::export($this->max);
        return "new {$className}({$min},{$max})";
    }

    #[Override]
    public function __toString(): string
    {
        return 'IntRange(' . ($this->min ?? 'min') . ', ' . ($this->max ?? 'max') . ')';
    }
}
