<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\ClientFactory;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\ContextFactory;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Contracts\OperationKeyGenerator;

return [

    /**
     * Define the path where to locate all queries and mutations.
     */
    'discovery_path' => app_path('Operations'),

    /**
     * Define a class name used to create the context for all operations.
     * It must implement Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\ContextFactory
     *
     * @see ContextFactory
     */
    'context' => null,

    /**
     * Define a class name used to create the client for all operations.
     * It must implement Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\ClientFactory
     *
     * Null uses the OperationClientFactory: the header `X-Client-Id: operations-spa`
     * selects the OperationSPAClient, everything else gets the NullClient.
     *
     * @see ClientFactory
     */
    'client' => null,

    /**
     * Defines the ID length to use for the cache keys. Usually 10 is enough. If you face
     * collisions, increase the number
     */
    'cache' => [
        'idLength' => 10,
    ],

    /**
     * Define the way to generate the key of the remote procedures.
     * This is used to limit the data that gets exposed to the client.
     *
     * Options:
     * - obfuscate: Uses sha256 with an optional pepper to hash both the name and namespace.
     * - plain
     * - custom: MUST define className
     */
    'key' => [
        /**
         * Options: obfuscate, plain, custom
         *
         * For obfuscate: you can define a pepper(string) to add randomness
         * For custom: MUST define className
         */
        'mode' => 'obfuscate',

        /**
         * Only relevant for mode 'obfuscate'
         */
        'pepper' => 'none',

        /**
         * Only relevantly for mode custom
         * Class must implement: Le0daniel\PhpTsBindings\Contracts\OperationKeyGenerator
         *
         * @see OperationKeyGenerator
         */
        'className' => null,
    ],

    /**
     * A list of global middleware class names run on every single Operation (Query and Command).
     * Every class must implement Le0daniel\PhpTsBindings\Contracts\MiddlewareContract.
     *
     * A global middleware cannot expose domain errors: a #[Throws(..., name: ...)] on its handle()
     * would leak one operation's vocabulary into all of them, so the declaration is ignored at
     * runtime and refused by code generation. Mapping onto a non-domain category - e.g.
     * #[Throws(Expired::class, type: ErrorType::AUTHENTICATION_ERROR)] - is fine.
     *
     * $next() always hands back an RpcSuccess or an RpcError - a failure further in is converted
     * before it reaches you, so post-processing runs either way.
     *
     * Usage:
     * ```php
     *   public function handle(mixed $input, Closure $next, mixed $context, ResolveInfo $info, Client $client): RpcSuccess|RpcError {
     *       // (...)
     *       $result = $next($input);
     *       // (...)
     *       return $result;
     *   }
     * ```
     *
     * @see MiddlewareContract
     */
    'middleware' => [],

    /**
     * Map your exceptions onto the server's built-in error categories. Anything not listed here and
     * neither marked with #[ExposeAs] nor named via #[Throws(..., name: ...)] is reported to the
     * client as an internal error.
     *
     * Matching is instanceof: listing a base class covers every subclass of it. A #[Throws]
     * declaration on the throwing scope wins over these lists.
     */
    'exceptions' => [
        'unauthenticated' => [
            AuthenticationException::class,
        ],
        'unauthorized' => [
            TokenMismatchException::class,
            AuthorizationException::class,
        ],
        'not_found' => [
            ModelNotFoundException::class,
            RecordNotFoundException::class,
            RecordsNotFoundException::class,
        ],
    ],
];
