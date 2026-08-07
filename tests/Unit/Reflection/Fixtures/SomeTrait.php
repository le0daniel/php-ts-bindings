<?php

declare(strict_types=1);

namespace Tests\Unit\Reflection\Fixtures;

trait SomeTrait
{
    public function traitMethod(): string
    {
        return 'trait';
    }
}
