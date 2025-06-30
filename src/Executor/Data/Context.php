<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

use Le0daniel\PhpTsBindings\Contracts\ExecutionContext;

final class Context implements ExecutionContext
{
    public function __construct(
        public bool $partialFailures = false,
        public bool $runConstraints = true,
    )
    {
    }

    /**
     * @var array<string|int>
     */
    private array $path = [];

    /**
     * @var array<string, Issue[]>
     */
    private(set) array $issues = [];

    public function enterPath(int|string $path): void
    {
        $this->path[] = $path;
    }

    public function leavePath(): void
    {
        array_pop($this->path);
    }

    private function pathAsString(): string
    {
        return $this->path
            ? implode('.', $this->path)
            : Issues::ROOT_PATH;
    }

    public function addIssue(Issue $issue): void
    {
        $this->issues[$this->pathAsString()][] = $issue;
    }

    public function removeCurrentIssues(): void
    {
        foreach ($this->issues as $path => $issues) {
            // This is needed as path '0' is transformed to int in php.
            if (str_starts_with((string) $path, $this->pathAsString())) {
                unset($this->issues[$path]);
            }
        }
    }
}