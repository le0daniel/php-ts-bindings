<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Exceptions;

use RuntimeException;

final class InvalidGeneratorDependencies extends RuntimeException
{
    /**
     * @param array<string> $messages
     */
    public function __construct(
        public readonly array $messages
    )
    {
        parent::__construct("Invalid generator dependencies");
    }
}