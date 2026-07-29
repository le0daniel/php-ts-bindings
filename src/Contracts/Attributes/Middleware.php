<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Middleware
{
    /**
     * @var list<class-string<MiddlewareContract<mixed>>>
     */
    public array $middleware;

    /**
     * @param class-string<MiddlewareContract<mixed>>|array<class-string<MiddlewareContract<mixed>>> $middleware
     */
    public function __construct(
        string|array $middleware,
    )
    {
        $this->middleware = is_array($middleware) ? array_values($middleware) : [$middleware];
    }
}