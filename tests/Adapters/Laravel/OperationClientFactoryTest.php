<?php

declare(strict_types=1);

namespace Tests\Adapters\Laravel;

use Illuminate\Http\Request;
use Le0daniel\PhpTsBindings\Adapters\Laravel\OperationClientFactory;
use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Client\OperationSPAClient;

test('an operations-spa client id selects the OperationSPAClient', function () {
    $request = Request::create('/query/docs.method', 'GET');
    $request->headers->set(OperationClientFactory::CLIENT_ID_HEADER, 'operations-spa');

    expect(new OperationClientFactory()->createClientFromHttpRequest($request))
        ->toBeInstanceOf(OperationSPAClient::class);
});

test('a request without a client id gets the NullClient', function () {
    $request = Request::create('/query/docs.method', 'GET');

    expect(new OperationClientFactory()->createClientFromHttpRequest($request))
        ->toBeInstanceOf(NullClient::class);
});

test('an unknown client id gets the NullClient', function () {
    $request = Request::create('/query/docs.method', 'GET');
    $request->headers->set(OperationClientFactory::CLIENT_ID_HEADER, 'operations-spa-2');

    expect(new OperationClientFactory()->createClientFromHttpRequest($request))
        ->toBeInstanceOf(NullClient::class);
});
