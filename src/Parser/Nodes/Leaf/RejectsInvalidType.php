<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;

trait RejectsInvalidType
{
    private function invalidType(string $expected, mixed $value, ExecutionContext $context): Value
    {
        $context->addIssue(Issue::invalidType($expected, $value));
        return Value::INVALID;
    }
}
