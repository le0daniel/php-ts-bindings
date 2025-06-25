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
         * Configure the middlewares that are required.
         * Global middlewares should be added via LaravelHttpController::registerCommandRoute()->middleware()
         *
         * Middlewares are applied as follows:
         * - namespace
         *
         * Those middlewares are run separately from the laravel middlewares. They are executed in the
         * LaravelHttpController itself.
         */
        "middlewares" => [
            // You can add middlewares by namespace. Just add the 'namespace' => []
            // Here to add any namespace specific middlewares.
        ],
    ],

];