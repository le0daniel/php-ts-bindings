<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

use BackedEnum;
use Throwable;
use UnitEnum;

final readonly class Issue
{
    public string $messageOrLocalizationKey;

    /**
     * @param string|UnitEnum $messageOrLocalizationKey
     * @param array<string, mixed> $debugInfo
     * @param Throwable|null $exception
     */
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

    public static function fromThrowable(Throwable $throwable, array $debugInfo = []): self
    {
        return new self(
            IssueMessage::INTERNAL_ERROR,
            $debugInfo,
            exception: $throwable,
        );
    }

    public static function internalError(array $debugInfo = []): self
    {
        return new self(
            IssueMessage::INTERNAL_ERROR,
            $debugInfo,
            exception: null,
        );
    }
}