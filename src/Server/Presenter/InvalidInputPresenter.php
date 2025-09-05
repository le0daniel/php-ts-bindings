<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Presenter;

use Le0daniel\PhpTsBindings\Contracts\ExceptionPresenter;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidInputException;
use Throwable;

final class InvalidInputPresenter implements ExceptionPresenter
{

    public function matches(Throwable $throwable, Definition $definition): bool
    {
        return $throwable instanceof InvalidInputException;
    }

    public function toTypeScriptDefinition(Definition $definition): string
    {
        return '{type:"INVALID_INPUT"; fields: Record<string, string[]>;}';
    }

    /*
     * @return array{status: 422, type: "INVALID_INPUT", fields: array<string, string[]>}
     */
    public function details(Throwable $throwable): array
    {
        /** @var InvalidInputException $throwable */

        return [
            'type' => 'INVALID_INPUT',
            'fields' => $throwable->failure->issues->serializeToFieldsArray(),
        ];
    }

    public static function statusCode(): int
    {
        return 422;
    }
}