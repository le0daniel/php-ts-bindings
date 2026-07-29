<?php declare(strict_types=1);

namespace Tests\Unit\Server\Client;

use Le0daniel\PhpTsBindings\Contracts\SerializableClient;
use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Data\Toast;
use Le0daniel\PhpTsBindings\Server\Data\ToastType;
use Tests\Mocks\InvalidationNamespace;

test('every directive is silently discarded', function () {
    $client = new NullClient();

    $client->toast(new Toast(ToastType::INFO, 'Heads up'));
    $client->success('Saved');
    $client->error('Failed');
    $client->warning('Careful');
    $client->alert('Heads up');
    $client->info('FYI');
    $client->redirect('/orders');
    $client->redirect('/logout', true);
    $client->invalidate(InvalidationNamespace::USERS, 'get');

    expect(true)->toBeTrue();
});

test('it is not serializable, which is what keeps __client off the response', function () {
    expect(new NullClient())->not->toBeInstanceOf(SerializableClient::class);
});
