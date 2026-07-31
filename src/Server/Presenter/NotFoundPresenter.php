<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Presenter;

use Le0daniel\PhpTsBindings\Contracts\ExceptionPresenter;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Override;
use Throwable;

final readonly class NotFoundPresenter implements ExceptionPresenter
{
    /**
     * @param list<class-string<Throwable>> $classNames
     */
    public function __construct(
        private readonly array $classNames
    )
    {
    }

    #[Override]
    public function matches(Throwable $throwable, Definition $definition): bool
    {
        return in_array(get_class($throwable), $this->classNames, true);
    }

    #[Override]
    public function toTypescriptDefinition(Definition $definition): string
    {
        return '{type: "NOT_FOUND";}';
    }

    /**
     * @return array{type: "NOT_FOUND"}
     */
    #[Override]
    public function details(Throwable $throwable): array
    {
        return [
            'type' => 'NOT_FOUND',
        ];
    }

    #[Override]
    public static function errorType(): ErrorType
    {
        return ErrorType::NOT_FOUND;
    }
}