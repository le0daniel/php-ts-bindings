<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;

trait RejectsInvalidType
{
    private function invalidType(string $expected, mixed $value, ExecutionContext $context): Value
    {
        $context->addIssue(new Issue(
            IssueMessage::INVALID_TYPE,
            [
                'message' => "Expected value of type {$expected}, got: " . gettype($value),
            ],
        ));
        return Value::INVALID;
    }
}
