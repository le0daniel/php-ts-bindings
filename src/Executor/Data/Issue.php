<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

use Throwable;

final readonly class Issue
{
    public function __construct(
        public string $messageOrLocalizationKey,
        public array $debugInfo = [],
        public ?Throwable $exception = null,
    )
    {
    }
}