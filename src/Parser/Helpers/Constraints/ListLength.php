<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Helpers\Constraints;

use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Parser\Contracts\Constraint;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

/**
 * Backs `non-empty-list<T>` and `non-empty-array<K, V>`, both of which PHPStan expresses as a
 * minimum of one element.
 *
 * It counts records as readily as lists: `non-empty-array<string, V>` parses to a RecordNode,
 * and both a record and a list are a plain PHP array on the wire, so one count() covers them.
 */
final readonly class ListLength implements Constraint
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
        if (!is_array($value)) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_TYPE,
                [
                    'message' => "Expected array, got: " . gettype($value),
                ],
            ));
            return false;
        }

        $count = count($value);

        if ($this->min !== null && $count < $this->min) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_MIN,
                [
                    'message' => "Expected at least {$this->min} elements, got: {$count}.",
                    'min' => $this->min,
                    'count' => $count,
                ],
            ));
            return false;
        }

        if ($this->max !== null && $count > $this->max) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_MAX,
                [
                    'message' => "Expected at most {$this->max} elements, got: {$count}.",
                    'max' => $this->max,
                    'count' => $count,
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
        return 'ListLength(' . ($this->min ?? 'min') . ', ' . ($this->max ?? 'max') . ')';
    }
}
