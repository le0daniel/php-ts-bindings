<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use Le0daniel\PhpTsBindings\Contracts\Attributes\ExposeAs;

#[ExposeAs(name: 'invalid_name')]
final class InvalidNameException extends \Exception
{
}
