<?php declare(strict_types=1);

use Le0daniel\PhpTsBindings\Typescript\Data\TypeRegistry;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnknownAliasException;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnsupportedTypeException;

test('starts empty', function () {
    $registry = new TypeRegistry();

    expect($registry->isEmpty())->toBeTrue()
        ->and($registry->toArray())->toBe([])
        ->and($registry->has('Anything'))->toBeFalse();
});

test('is seeded from the constructor', function () {
    $registry = new TypeRegistry(['Email' => 'string & Brand<"email">']);

    expect($registry->isEmpty())->toBeFalse()
        ->and($registry->has('Email'))->toBeTrue()
        ->and($registry->get('Email'))->toBe('string & Brand<"email">');
});

test('stores and reads back a definition', function () {
    $registry = new TypeRegistry();
    $registry->set('Email', 'string & Brand<"email">');

    expect($registry->has('Email'))->toBeTrue()
        ->and($registry->get('Email'))->toBe('string & Brand<"email">')
        ->and($registry->isEmpty())->toBeFalse();
});

test('accepts the identical definition twice', function () {
    $registry = new TypeRegistry();
    $registry->set('Email', 'string & Brand<"email">');
    $registry->set('Email', 'string & Brand<"email">');

    expect($registry->toArray())->toBe(['Email' => 'string & Brand<"email">']);
});

test('throws when an alias is rebound to a different definition', function () {
    $registry = new TypeRegistry();
    $registry->set('Email', 'string & Brand<"email">');

    expect(fn() => $registry->set('Email', 'number & Brand<"email">'))
        ->toThrow(UnsupportedTypeException::class, 'Type alias Email has conflicting definitions');
});

test('a seed array cannot conflict with itself, only a later set() can', function () {
    $registry = new TypeRegistry(['Email' => 'string & Brand<"email">']);

    // Duplicate keys collapse inside an array literal, so the last one simply wins.
    expect(fn() => new TypeRegistry([...$registry->toArray(), 'Email' => 'number']))
        ->not->toThrow(UnsupportedTypeException::class);

    expect(fn() => $registry->set('Email', 'number'))
        ->toThrow(UnsupportedTypeException::class);
});

test('throws when reading an alias that was never defined', function () {
    $registry = new TypeRegistry(['Email' => 'string & Brand<"email">']);

    expect(fn() => $registry->get('Missing'))
        ->toThrow(UnknownAliasException::class, "Unknown type alias 'Missing'. Call has() before get(). Known aliases: Email.");
});

test('names no aliases when reading from an empty registry', function () {
    expect(fn() => new TypeRegistry()->get('Missing'))
        ->toThrow(UnknownAliasException::class, 'Known aliases: none.');
});

test('returns definitions sorted by alias', function () {
    $registry = new TypeRegistry();
    $registry->set('Zulu', 'string');
    $registry->set('Alpha', 'number');
    $registry->set('Mike', 'boolean');

    expect($registry->toArray())->toBe([
        'Alpha' => 'number',
        'Mike' => 'boolean',
        'Zulu' => 'string',
    ]);
});

test('a clone does not share state with its original', function () {
    $original = new TypeRegistry(['Email' => 'string & Brand<"email">']);

    $copy = clone $original;
    $copy->set('Token', 'string & Brand<"token">');

    expect($copy->has('Token'))->toBeTrue()
        ->and($original->has('Token'))->toBeFalse()
        ->and($original->toArray())->toBe(['Email' => 'string & Brand<"email">']);
});
