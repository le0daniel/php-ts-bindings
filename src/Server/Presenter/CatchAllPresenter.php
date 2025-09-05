<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Presenter;

use Le0daniel\PhpTsBindings\Contracts\ExceptionPresenter;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Throwable;

final class CatchAllPresenter implements ExceptionPresenter
{

    public function matches(Throwable $throwable, Definition $definition): bool
    {
        return true;
    }

    public function toTypeScriptDefinition(Definition $definition): string
    {
        return '{type: "INTERNAL_SERVER_ERROR"}';
    }

    /*
     * @return array{status: 500, type: "INTERNAL_SERVER_ERROR"}
     */
    public function details(Throwable $throwable): array
    {
        return [
            'type' => 'INTERNAL_SERVER_ERROR',
        ];
    }

    public static function errorType(): ErrorType
    {
        return ErrorType::INTERNAL_ERROR;
    }
}