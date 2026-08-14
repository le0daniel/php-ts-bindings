<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts;

use Illuminate\Http\Request;
use Le0daniel\PhpTsBindings\Contracts\Client;

interface ClientFactory
{
    /**
     * Given a Laravel HTTP request for a query or command, create the Client the
     * operation talks to. A Client is required; return a NullClient to discard
     * every directive.
     */
    public function createClientFromHttpRequest(Request $request): Client;
}
