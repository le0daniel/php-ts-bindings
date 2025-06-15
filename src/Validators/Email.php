<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Validators;

use Attribute;
use Le0daniel\PhpTsBindings\Contracts\Constraint;
use Le0daniel\PhpTsBindings\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Email implements Constraint
{
    public function validate(mixed $value, ExecutionContext $context): bool
    {
        if (!is_string($value)) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_TYPE,
                [
                    "message" => "Expected string, got: " . gettype($value),
                ],
            ));
            return false;
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_EMAIL,
                [
                    "message" => "Expected valid email address, got: '{$value}'",
                ]
            ));
            return false;
        }

        return true;
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        return "new {$className}()";
    }
}