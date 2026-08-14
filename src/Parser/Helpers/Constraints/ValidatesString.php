<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Helpers\Constraints;

use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;

/**
 * A string constraint always sits on top of a StringNode, which has already rejected non-strings
 * by the time the constraint runs. The guard exists for the case where it has not: a constraint
 * is an ordinary object and nothing stops it being validated against an arbitrary value.
 */
trait ValidatesString
{
    /**
     * @phpstan-assert-if-true string $value
     */
    private function isString(mixed $value, ExecutionContext $context): bool
    {
        if (is_string($value)) {
            return true;
        }

        $context->addIssue(new Issue(
            IssueMessage::INVALID_TYPE,
            [
                'message' => 'Expected string, got: '.gettype($value),
            ],
        ));

        return false;
    }
}
