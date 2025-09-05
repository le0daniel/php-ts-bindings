<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Presenter;

use Le0daniel\PhpTsBindings\Contracts\ExceptionPresenter;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Throwable;

final class UnauthenticatedPresenter implements ExceptionPresenter
{
    /**
     * @param list<class-string<\Throwable>> $unauthenticatedClassNames
     */
    public function __construct(
        private readonly array $unauthenticatedClassNames
    )
    {
    }

    public function matches(Throwable $throwable, Definition $definition): bool
    {
        return in_array(get_class($throwable), $this->unauthenticatedClassNames);
    }

    public function toTypeScriptDefinition(Definition $definition): string
    {
        return '{type: "UNAUTHENTICATED";}';
    }

    /*
     * @return array{status: 401, type: "UNAUTHENTICATED"}
     */
    public function details(Throwable $throwable): array
    {
        return [
            'type' => 'UNAUTHENTICATED',
        ];
    }

    public static function statusCode(): int
    {
        return 401;
    }
}