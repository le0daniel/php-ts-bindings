<?php

namespace Le0daniel\PhpTsBindings\Contracts;

use Stringable;

/**
 * The stringable method should return all parameters that influence the type.
 * It is used internally to generate the hash of the type. Best is if this is humanly readable
 * but not required as long as it is unique for the given instance properties.
 */
interface NodeInterface extends Stringable, ExportableToPhpCode
{
}