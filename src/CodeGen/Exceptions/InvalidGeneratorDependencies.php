<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Exceptions;

final class InvalidGeneratorDependencies extends CodeGenException
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