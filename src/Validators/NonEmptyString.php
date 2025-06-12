<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Validators;

use Le0daniel\PhpTsBindings\Contracts\Constraint;
use Le0daniel\PhpTsBindings\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final class NonEmptyString implements Constraint
{
    public function validate(mixed $value, ExecutionContext $context): bool
    {
        if (!is_string($value)) {
            $context->addIssue(new Issue(
                'validation.invalid_type',
                [
                    "message" => "Expected string, got: " . gettype($value),
                ]
            ));
            return false;
        }

        if (empty($value)) {
            $context->addIssue(new Issue(
                'validation.not_empty_string',
                [
                    "message" => "Expected non-empty string, got: '{$value}'",
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