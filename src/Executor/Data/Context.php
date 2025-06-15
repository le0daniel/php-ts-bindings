<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

use Le0daniel\PhpTsBindings\Contracts\ExecutionContext;

final class Context implements ExecutionContext
{
    public function __construct()
    {
    }

    public const string ROOT_PATH = '__root';

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
            : self::ROOT_PATH;
    }

    public function addIssue(Issue $issue): void
    {
        $this->issues[$this->pathAsString()][] = $issue;
    }

    /**
     * @return Issue[]
     */
    public function getIssuesAt(string $path): array
    {
        return $this->issues[$path] ?? [];
    }
}