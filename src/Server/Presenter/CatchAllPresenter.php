<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Presenter;

use Le0daniel\PhpTsBindings\Contracts\ExceptionPresenter;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Override;
use Throwable;

final readonly class CatchAllPresenter implements ExceptionPresenter
{
    #[Override]
    public function matches(Throwable $throwable, Definition $definition): bool
    {
        return true;
    }

    #[Override]
    public function toTypescriptDefinition(Definition $definition): string
    {
        return '{type: "INTERNAL_SERVER_ERROR"}';
    }

    /**
     * @return array{type: "INTERNAL_SERVER_ERROR"}
     */
    #[Override]
    public function details(Throwable $throwable): array
    {
        return [
            'type' => 'INTERNAL_SERVER_ERROR',
        ];
    }

    #[Override]
    public static function errorType(): ErrorType
    {
        return ErrorType::INTERNAL_ERROR;
    }
}