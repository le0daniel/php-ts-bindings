<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Context;

use Illuminate\Http\Request;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\ContextFactory;

final class NullContextFactory implements ContextFactory
{

    public function createContextFromHttpRequest(Request $request): null
    {
        return null;
    }
}