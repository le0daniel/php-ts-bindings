<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Helpers\Constraints;

use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Parser\Contracts\Constraint;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

/**
 * Backs `lowercase-string`.
 *
 * strtolower(), not mb_strtolower(): PHPStan defines the type against PHP's own ASCII-only
 * case folding, so a multibyte uppercase letter is a lowercase-string to PHPStan and must be
 * one here too. The empty string qualifies.
 */
final readonly class LowercaseString implements Constraint
{
    use ValidatesString;

    #[Override]
    public function validate(mixed $value, ExecutionContext $context): bool
    {
        if (!$this->isString($value, $context)) {
            return false;
        }

        if (strtolower($value) !== $value) {
            $context->addIssue(new Issue(
                IssueMessage::NOT_LOWERCASE_STRING,
                [
                    "message" => "Expected lowercase string, got: '{$value}'",
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
        return 'LowercaseString';
    }
}
