<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Constraints;

use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Parser\Contracts\Constraint;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

/**
 * Backs `numeric-string`. PHPStan defines it as a string is_numeric() accepts, which includes
 * leading whitespace, a sign, exponents and hex-free floats - the value stays a string either
 * way, so no coercion happens here.
 */
final readonly class NumericString implements Constraint
{
    use ValidatesString;

    #[Override]
    public function validate(mixed $value, ExecutionContext $context): bool
    {
        if (!$this->isString($value, $context)) {
            return false;
        }

        if (!is_numeric($value)) {
            $context->addIssue(new Issue(
                IssueMessage::NOT_NUMERIC_STRING,
                [
                    "message" => "Expected numeric string, got: '{$value}'",
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
        return 'NumericString';
    }
}
