<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\HashSha256KeyGenerator;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\PlainlyExposedKeyGenerator;

test('the name segment differs per namespace', function () {
    // Hashing the name on its own gave every `get` in the application the identical segment, so
    // learning one key told you the segment for that method name everywhere else.
    $generator = new HashSha256KeyGenerator('pepper');

    [, $usersGet] = explode('.', $generator->generateKey('users', 'get'));
    [, $ordersGet] = explode('.', $generator->generateKey('orders', 'get'));

    expect($usersGet)->not->toBe($ordersGet);
});

test('the namespace segment is still stable across the operations in it', function () {
    $generator = new HashSha256KeyGenerator('pepper');

    [$usersGet] = explode('.', $generator->generateKey('users', 'get'));
    [$usersCreate] = explode('.', $generator->generateKey('users', 'create'));

    expect($usersGet)->toBe($usersCreate);
});

test('the pepper changes every key', function () {
    expect(new HashSha256KeyGenerator('a')->generateKey('users', 'get'))
        ->not->toBe(new HashSha256KeyGenerator('b')->generateKey('users', 'get'));
});

test('key generation is deterministic', function () {
    expect(new HashSha256KeyGenerator('pepper')->generateKey('users', 'get'))
        ->toBe(new HashSha256KeyGenerator('pepper')->generateKey('users', 'get'));
});

test('segment lengths are honoured', function () {
    $key = new HashSha256KeyGenerator('pepper', namespaceLength: 4, fnNameLength: 6)
        ->generateKey('users', 'get');

    [$namespace, $name] = explode('.', $key);

    expect($namespace)->toHaveLength(4)
        ->and($name)->toHaveLength(6);
});

test('the plain generator exposes namespace and name verbatim', function () {
    expect(new PlainlyExposedKeyGenerator()->generateKey('users', 'get'))->toBe('users.get');
});

test('a query and a command with the same name are distinct registry keys', function () {
    expect(OperationType::QUERY->registryKey('users.get'))
        ->not->toBe(OperationType::COMMAND->registryKey('users.get'));
});
