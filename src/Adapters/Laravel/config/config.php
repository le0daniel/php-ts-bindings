<?php declare(strict_types=1);

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
];