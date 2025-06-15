<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings;

use Le0daniel\PhpTsBindings\Parser\TypeParser;

final class BindingsManager
{

    public function __construct(
        // private readonly TypeParser $parser,
        // private ?SchemaRegistry $registry,
    )
    {
    }

    /**
     * @return void
     */
    public function execute(mixed $input, mixed $context)
    {
        // Discover if needed
        // Parse input with executor
        // Execute on method
        // Serialize on success
        // Return serialized output
    }
}