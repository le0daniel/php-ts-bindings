<?php declare(strict_types=1);

namespace Tests\Feature\Operations;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;

final class NameCheckingMiddleware
{
    #[Throws(InvalidNameException::class)]
    public function handle(array $input, \Closure $next)
    {
        if ($input['name'] === 'invalid') {
            throw new InvalidNameException();
        }

        return $next($input);
    }
}