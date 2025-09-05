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
     */
    "key" => [
        "mode" => "obfuscate",

        /**
         * Only relevant for mode 'obfuscate'
         */
        "pepper" => "none",
    ],

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