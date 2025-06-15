<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Validators;

use Attribute;
use Le0daniel\PhpTsBindings\Contracts\Constraint;
use Le0daniel\PhpTsBindings\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class NonFalsyStringValidator implements Constraint
{

    public function validate(mixed $value, ExecutionContext $context): bool
    {
        if (!is_string($value)) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_TYPE,
                [
                    "message" => "Expected string, got: " . gettype($value),
                    "value" => $value,
                ]
            ));
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

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        return "new {$className}()";
    }
}