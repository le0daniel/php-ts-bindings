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

    /**
     * The value did not have the declared type. Every handler and leaf reports this the same way,
     * so a failure is never returned without a diagnostic the client can act on.
     */
    public static function invalidType(string $expected, mixed $value): self
    {
        return new self(
            IssueMessage::INVALID_TYPE,
            ['message' => "Expected value of type {$expected}, got: " . gettype($value)],
        );
    }

    /**
     * @param list<string> $messages
     * @return list<Issue>
     */
    public static function fromMessageArray(array $messages): array
    {
        return array_map(fn(string $message) => new self($message), $messages);
    }

    /**
     * @param Throwable $throwable
     * @param array<string, mixed> $debugInfo
     * @return self
     */
    public static function fromThrowable(Throwable $throwable, array $debugInfo = []): self
    {
        return new self(
            IssueMessage::INTERNAL_ERROR,
            $debugInfo,
            exception: $throwable,
        );
    }

    /**
     * @param array<string, mixed> $debugInfo
     * @return self
     */
    public static function internalError(array $debugInfo = []): self
    {
        return new self(
            IssueMessage::INTERNAL_ERROR,
            $debugInfo,
            exception: null,
        );
    }
}