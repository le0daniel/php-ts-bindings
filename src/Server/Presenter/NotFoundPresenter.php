<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Presenter;

use Le0daniel\PhpTsBindings\Contracts\ExceptionPresenter;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Throwable;

final class NotFoundPresenter implements ExceptionPresenter
{
    /**
     * @param list<class-string<Throwable>> $classNames
     */
    public function __construct(
        private readonly array $classNames
    )
    {
    }

    public function matches(Throwable $throwable, Definition $definition): bool
    {
        return in_array(get_class($throwable), $this->classNames);
    }

    public function toTypeScriptDefinition(Definition $definition): string
    {
        return '{code: 404;type: "NOT_FOUND";}';
    }

    /*
     * @return array{status: 404, type: "NOT_FOUND"}
     */
    public function details(Throwable $throwable): array
    {
        return [
            'code' => 404,
            'type' => 'NOT_FOUND',
        ];
    }

    public static function statusCode(): int
    {
        return 404;
    }
}