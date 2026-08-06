<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Tests\Mocks\ValueObjects\Email;
use Tests\Mocks\ValueObjects\Slug;
use Tests\Mocks\ValueObjects\UserId;

/**
 * Operations whose schemas mix branded value objects (UserId, Email) with an unbranded one (Slug),
 * so code generation has to both reference and declare aliases.
 */
final class UserOperations
{
    /**
     * @param  array{id: UserId}  $input
     * @return array{email: Email, slug: Slug}
     */
    #[Query('users')]
    public function get(array $input): array
    {
        return [
            'email' => Email::fromStringValue('user@example.com'),
            'slug' => Slug::fromStringValue("user-{$input['id']->toIntValue()}"),
        ];
    }

    /**
     * @param  array{name: string}  $input
     * @return array{id: UserId}
     */
    #[Command('users')]
    public function create(array $input): array
    {
        return ['id' => UserId::fromIntValue(strlen($input['name']))];
    }
}
