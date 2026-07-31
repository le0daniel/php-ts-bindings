<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Constraints;

use Attribute;
use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Parser\Contracts\Constraint;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class NonEmptyString implements Constraint
{
    #[Override]
    public function validate(mixed $value, ExecutionContext $context): bool
    {
        if (!is_string($value)) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_TYPE,
                [
                    "message" => "Expected string, got: " . gettype($value),
                ]
            ));
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
        $className = PHPExport::absolute(self::class);
        return "new {$className}()";
    }
}