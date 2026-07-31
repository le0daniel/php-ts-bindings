<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Presenter;

use Le0daniel\PhpTsBindings\Contracts\ExceptionPresenter;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Override;
use Throwable;

final readonly class UnauthenticatedPresenter implements ExceptionPresenter
{
    /**
     * @param list<class-string<\Throwable>> $unauthenticatedClassNames
     */
    public function __construct(
        private readonly array $unauthenticatedClassNames
    )
    {
    }

    #[Override]
    public function matches(Throwable $throwable, Definition $definition): bool
    {
        return in_array(get_class($throwable), $this->unauthenticatedClassNames, true);
    }

    #[Override]
    public function toTypescriptDefinition(Definition $definition): string
    {
        return '{type: "UNAUTHENTICATED";}';
    }

    /**
     * @return array{type: "UNAUTHENTICATED"}
     */
    #[Override]
    public function details(Throwable $throwable): array
    {
        return [
            'type' => 'UNAUTHENTICATED',
        ];
    }

    #[Override]
    public static function errorType(): ErrorType
    {
        return ErrorType::AUTHENTICATION_ERROR;
    }
}