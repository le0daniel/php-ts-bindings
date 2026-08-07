<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks\Inherited;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;

/**
 * The operation lives here but is registered through a subclass in another namespace. Both PHPDoc
 * types name InheritedResult unqualified, so they only resolve against THIS file - which is the
 * point: a scope taken from the registered class would look for them next to the subclass.
 */
abstract class BaseOperations
{
    /**
     * @param  array{result: InheritedResult}  $input
     * @return InheritedResult
     */
    #[Query('inherited')]
    public function get(array $input): InheritedResult
    {
        return $input['result'];
    }
}
