<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Presenter;

use Le0daniel\PhpTsBindings\Contracts\ExceptionPresenter;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidInputException;
use Override;
use Throwable;

final readonly class InvalidInputPresenter implements ExceptionPresenter
{

    #[Override]
    public function matches(Throwable $throwable, Definition $definition): bool
    {
        return $throwable instanceof InvalidInputException;
    }

    #[Override]
    public function toTypescriptDefinition(Definition $definition): string
    {
        return '{type:"INVALID_INPUT"; fields: Record<string, string[]>;}';
    }

    /**
     * @return array{type: "INVALID_INPUT", fields: array<string, string[]>}
     */
    #[Override]
    public function details(Throwable $throwable): array
    {
        /** @var InvalidInputException $throwable */

        return [
            'type' => 'INVALID_INPUT',
            'fields' => $throwable->failure->issues->serializeToFieldsArray(),
        ];
    }

    #[Override]
    public static function errorType(): ErrorType
    {
        return ErrorType::INVALID_INPUT;
    }
}