<?php declare(strict_types=1);

return [

    /**
     * Define the path where to locate all query and mutations.
     */
    "discovery_path" => app_path('Operations'),

    /**
     *
     */
    "http" => [
        // By default, they will be added as a base Route not grouped.
        // This means /query/{fcn} and /command/{fcn}
        "base_route" => null,

        /**
         * Configure the middlewares that are required
         *
         * Middlewares are applied as follows:
         * - query|command
         * - namespace
         */
        "middlewares" => [
            // Middlewares that are only affecting the query query.
            "query" => [

            ],

            // Middlewares that are only affecting the command part. This is where you should add the
            // verify CSRF token.
            "command" => [

            ],

            // You can add middlewares by namespace. Just add the 'namespace' => []
            // Here to add any namespace specific middlewares.
        ],
    ],

];