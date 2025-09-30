<?php declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Session\TokenMismatchException;

return [

    /**
     * Define the path where to locate all query and mutations.
     */
    "discovery_path" => app_path('Operations'),

    /**
     * Define a class name used to create the context for all operations.
     * It must implement Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\ContextFactory
     *
     * @see Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\ContextFactory
     */
    "context" => null,

    /**
     * Define the way to generate the key of the remote procedures.
     * This is used to limit the data that gets exposed to the client.
     *
     * Options:
     * - obfuscate: Uses sha256 with an optional pepper to hash both the name and namespace.
     * - plain
     * - custom: MUST define className
     */
    "key" => [
        "mode" => "obfuscate",

        /**
         * Only relevant for mode 'obfuscate'
         */
        "pepper" => "none",

        /**
         * Only relevantly for mode custom
         * Class must implement: Le0daniel\PhpTsBindings\Contracts\OperationKeyGenerator
         */
        "className" => null,
    ],

    /**
     * A list of global middleware class names run on every single Operation (Query and Command)
     * Must implement:
     * - public handle(mixed $input, Closure $next, mixed $context, ResolveInfo $info, Client $client): RpcSuccess|RpcError
     *
     * Usage:
     * ```php
     *   public function handle(mixed $input, Closure $next) {
     *       // (...)
     *       $result = $next($input);
     *       // (...)
     *       return $result;
     *   }
     * ```
     */
    "middleware" => [],

    /**
     * Map your exceptions to framework-specific exceptions.
     */
    "exceptions" => [
        "unauthenticated" => [
            AuthenticationException::class,
        ],
        "unauthorized" => [
            TokenMismatchException::class,
            AuthorizationException::class,
        ],
        "not_found" => [
            ModelNotFoundException::class,
            RecordNotFoundException::class,
            RecordsNotFoundException::class,
        ],
    ],
];