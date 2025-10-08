<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data\Exceptions;

use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Issues;

final class InvalidInputException extends \Exception
{
    public function __construct(public readonly Failure $failure)
    {
        parent::__construct("Input validation failed", 422, $this->failure);
    }

    /**
     * @param array<string, string|string[]> $issuesMap
     * @return self
     */
    public static function createFromMessages(array $issuesMap): self
    {
        return new self(
            new Failure(Issues::fromMessages($issuesMap)),
        );
    }
}