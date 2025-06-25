<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Middleware
{
    public array $middleware;

    /**
     * @param string|array<string> $middleware
     */
    public function __construct(
        string|array $middleware,
    )
    {
        $this->middleware = is_array($middleware) ? $middleware : [$middleware];
    }
}