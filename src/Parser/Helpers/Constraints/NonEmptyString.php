<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Helpers\Constraints;

use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Parser\Contracts\Constraint;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

/**
 * Backs `non-empty-string`, and the non-empty half of `non-empty-lowercase-string` and
 * `non-empty-uppercase-string`.
 */
final readonly class NonEmptyString implements Constraint
{
    use ValidatesString;

    #[Override]
    public function validate(mixed $value, ExecutionContext $context): bool
    {
        if (!$this->isString($value, $context)) {
            return false;
        }

        // Not empty(): "0" is empty() but is a valid non-empty-string. Rejecting it here would be
        // stricter than the type this constraint backs - that is what non-falsy-string is for.
        if ($value === '') {
            $context->addIssue(new Issue(
                IssueMessage::NOT_EMPTY_STRING,
                [
                    "message" => "Expected non-empty string, got an empty string.",
                ]
            ));
            return false;
        }

        return true;
    }

    #[Override]
    public function exportPhpCode(): string
    {
        return 'new ' . PHPExport::absolute(self::class) . '()';
    }

    #[Override]
    public function __toString(): string
    {
        return 'NonEmptyString';
    }
}
