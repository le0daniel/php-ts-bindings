<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

final class Failure
{
    /**
     * @param array<string, Issue[]> $issues
     */
    public function __construct(
        public array $issues,
    )
    {
    }

    public function serializeIssuesToString(): string
    {
        $messages = [];
        foreach ($this->issues as $path => $issues) {
            $imploded = implode(',', array_map(fn(Issue $issue) => $issue->messageOrLocalizationKey, $issues));
            $issues[] = "At {$path}: {$imploded}";
        }
        return implode('. ', $messages);
    }
}