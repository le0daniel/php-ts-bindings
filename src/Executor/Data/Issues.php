<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

final class Issues
{
    public const string ROOT_PATH = '__root';

    /**
     * @param array<string, Issue[]> $issuesMap
     */
    public function __construct(
        public readonly array $issuesMap = [],
    )
    {
    }

    public function isEmpty(): bool
    {
        return empty($this->issuesMap);
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
        return array_merge(...array_values($this->issuesMap));
    }

    /**
     * @return array<string, list<string>>
     */
    public function serializeToFieldsArray(): array
    {
        return array_map(function ($issues) {
            return array_map(fn(Issue $issue) => $issue->messageOrLocalizationKey, $issues);
        }, $this->issuesMap);
    }

    public function serializeToCompleteString(): string
    {
        $messages = [];
        foreach ($this->issuesMap as $path => $issues) {
            $imploded = implode(',', array_map(fn(Issue $issue) => $issue->messageOrLocalizationKey, $issues));
            $messages[] = "At {$path}: {$imploded}";
        }
        return implode('. ', $messages);
    }
}