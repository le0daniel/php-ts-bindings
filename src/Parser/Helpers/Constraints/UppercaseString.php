<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Helpers\Constraints;

use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Parser\Contracts\Constraint;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

/**
 * Backs `uppercase-string`. The mirror of LowercaseString, ASCII-only for the same reason.
 */
final readonly class UppercaseString implements Constraint
{
    use ValidatesString;

    #[Override]
    public function validate(mixed $value, ExecutionContext $context): bool
    {
        if (! $this->isString($value, $context)) {
            return false;
        }

        if (strtoupper($value) !== $value) {
            $context->addIssue(new Issue(
                IssueMessage::NOT_UPPERCASE_STRING,
                [
                    'message' => "Expected uppercase string, got: '{$value}'",
                ]
            ));

            return false;
        }

        return true;
    }

    #[Override]
    public function exportPhpCode(): string
    {
        return 'new '.PHPExport::absolute(self::class).'()';
    }

    #[Override]
    public function __toString(): string
    {
        return 'UppercaseString';
    }
}
