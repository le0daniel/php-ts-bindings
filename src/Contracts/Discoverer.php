<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

use ReflectionClass;

interface Discoverer
{
    public function discover(ReflectionClass $class): void;
}