<?php declare(strict_types=1);

namespace Tests\Feature\Operations;

use Le0daniel\PhpTsBindings\Contracts\ClientAwareException;

final class InvalidNameException extends \Exception implements ClientAwareException
{

    public static function type(): string
    {
        return 'invalid_name';
    }
}