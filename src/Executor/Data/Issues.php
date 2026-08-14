<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

final readonly class Issues
{
    public const string ROOT_PATH = '__root';

    /**
     * Paths are written as strings and read back as array keys, so a path of digits - 'items.0' is
     * nested and stays a string, but a bare '0' is not - comes back as an int. See Context::$issues.
     *
     * @param  array<array-key, list<Issue>>  $issuesMap
     */
    public function __construct(
        public readonly array $issuesMap = [],
    ) {
    }

    /**
     * @param  array<string, string|string[]>  $issuesMap
     */
    public static function fromMessages(array $issuesMap): self
    {
        return new self(
            array_map(
                fn (string|array $issues) => Issue::fromMessageArray(
                    is_array($issues) ? array_values($issues) : [$issues],
                ),
                $issuesMap
            )
        );
    }

    public function isEmpty(): bool
    {
        return count($this->issuesMap) === 0;
    }

    /** @return list<Issue> */
    public function at(?string $path): array
    {
        $path ??= self::ROOT_PATH;

        return $this->issuesMap[$path] ?? [];
    }

    /** @return list<Issue> */
    public function allFlat(): array
    {
        return $this->issuesMap === []
            ? []
            : array_merge(...array_values($this->issuesMap));
    }

    /**
     * @return array<string, string[]>
     */
    public function serializeToFieldsArray(): array
    {
        return array_map(function ($issues) {
            return array_map(fn (Issue $issue) => $issue->messageOrLocalizationKey, $issues);
        }, $this->issuesMap);
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function serializeToDebugFields(): array
    {
        return array_map(function (array $issues): array {
            return array_map(fn (Issue $issue): array => [
                'message' => $issue->messageOrLocalizationKey,
                'debugInfo' => $issue->debugInfo,
                'exception' => $issue->exception ? [
                    'class' => get_class($issue->exception),
                    'message' => $issue->exception->getMessage(),
                    'code' => $issue->exception->getCode(),
                    'file' => $issue->exception->getFile(),
                    'line' => $issue->exception->getLine(),
                    'trace' => $issue->exception->getTrace(),
                ] : null,
            ], $issues);
        }, $this->issuesMap);
    }

    public function serializeToCompleteString(): string
    {
        $messages = [];
        foreach ($this->issuesMap as $path => $issues) {
            $imploded = implode(',', array_map(fn (Issue $issue) => $issue->messageOrLocalizationKey, $issues));
            $messages[] = "At {$path}: {$imploded}";
        }

        return implode('. ', $messages);
    }
}
