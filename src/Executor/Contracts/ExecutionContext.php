<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Contracts;

use Le0daniel\PhpTsBindings\Executor\Data\Issue;

interface ExecutionContext
{
    public function addIssue(Issue $issue): void;
}