<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts;

use Illuminate\Http\Request;


interface ContextFactory
{
    /**
     * Given a Laravel HTTP request for a query or command, create the correct context.
     * It should be an object.
     */
    public function createContextFromHttpRequest(Request $request): mixed;
}