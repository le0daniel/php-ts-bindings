<?php declare(strict_types=1);

namespace Tests\Unit\Parser\Data\Stubs;

final readonly class ReadonlyOutputFields
{
    public string $name;
    public function __construct(public string $email)
    {
        $this->name = 'name';
    }
}