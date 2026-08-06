<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Override;

final class Context implements ExecutionContext
{
    public function __construct(
        public bool $partialFailures = false,
        public bool $coercePrimitives = false,
    ) {
    }

    /**
     * @var array<string|int>
     */
    private array $path = [];

    /**
     * @var array<string, list<Issue>>
     */
    public private(set) array $issues = [];

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

    #[Override]
    public function addIssue(Issue $issue): void
    {
        $this->issues[$this->pathAsString()][] = $issue;
    }

    /**
     * Discards the issues recorded at the current path and everything nested below it - what a
     * union does once an arm matches, so the arms it rejected leave no diagnostics behind.
     *
     * Matching is by path segment, not by string prefix: 'items.0' merely starts with the text
     * 'item' and must survive, while the root path is spelled '__root' and is a prefix of no
     * nested path at all, so a raw prefix test would clear nothing there.
     */
    public function removeCurrentIssues(): void
    {
        if ($this->path === []) {
            $this->issues = [];

            return;
        }

        $current = $this->pathAsString();
        foreach ($this->issues as $path => $issues) {
            if ($path === $current || str_starts_with($path, "{$current}.")) {
                unset($this->issues[$path]);
            }
        }
    }
}
