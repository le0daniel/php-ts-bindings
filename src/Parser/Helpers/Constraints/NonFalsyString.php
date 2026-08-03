<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Helpers\Constraints;

use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Parser\Contracts\Constraint;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

/**
 * Backs `non-falsy-string` and its alias `truthy-string`. It differs from NonEmptyString by
 * exactly one value: "0" is a valid non-empty-string but not a valid non-falsy-string.
 */
final readonly class NonFalsyString implements Constraint
{
    use ValidatesString;

    #[Override]
    public function validate(mixed $value, ExecutionContext $context): bool
    {
        if (!$this->isString($value, $context)) {
            return false;
        }

        if (!$value) {
            $context->addIssue(new Issue(
                IssueMessage::FALSY_STRING,
                [
                    "message" => "Expected non-falsy string, got: '{$value}'",
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
        return 'NonFalsyString';
    }
}
