<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

use BackedEnum;
use Throwable;
use UnitEnum;

final readonly class Issue
{
    public string $messageOrLocalizationKey;

    public function __construct(
        string|UnitEnum   $messageOrLocalizationKey,
        public array      $debugInfo = [],
        public ?Throwable $exception = null,
    )
    {
        $this->messageOrLocalizationKey = match (true) {
            $messageOrLocalizationKey instanceof BackedEnum => (string) $messageOrLocalizationKey->value,
            $messageOrLocalizationKey instanceof UnitEnum => $messageOrLocalizationKey->name,
            default => $messageOrLocalizationKey,
        };
    }
}