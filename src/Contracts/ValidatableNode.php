<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

interface ValidatableNode
{
    public function validate(): void;
}