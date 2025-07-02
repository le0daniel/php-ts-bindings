<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts;

use Illuminate\Http\Request;


interface ContextFactory
{
    /**
     * Given an HTTP request for an action or command, create the correct context.
     * It must be an object.
     */
    public function createContextFromHttpRequest(Request $request): mixed;
}