<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel;

use Illuminate\Http\Request;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\ClientFactory;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Client\OperationSPAClient;
use Override;

final readonly class OperationClientFactory implements ClientFactory
{
    public const string CLIENT_ID_HEADER = 'X-Client-Id';

    #[Override]
    public function createClientFromHttpRequest(Request $request): Client
    {
        if ($request->header(self::CLIENT_ID_HEADER) === 'operations-spa') {
            return new OperationSPAClient();
        }

        return new NullClient();
    }
}
