<?php

declare(strict_types=1);

namespace Tests\Integration;

use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\PlainlyExposedKeyGenerator;
use Le0daniel\PhpTsBindings\Server\Operations\CachedOperationRegistry;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedOperationRegistry;
use Le0daniel\PhpTsBindings\Server\Server;

/**
 * JSON string in, envelope JSON string out. Every call executes the operation twice - once on the
 * eagerly discovered registry, once on a CachedOperationRegistry written to disk with
 * writeToCache() and loaded back with require, the production cache path - and asserts both
 * envelopes are byte-identical before returning one. A test asserting the returned string
 * therefore always pins cached and non-cached behavior at once.
 */
final class IntegrationHarness
{
    private static ?EagerlyLoadedOperationRegistry $eagerRegistry = null;

    private static ?CachedOperationRegistry $cachedRegistry = null;

    public static function queryJson(string $key, ?string $json = null, bool $coerceQueryInput = false): string
    {
        return self::execute(OperationType::QUERY, $key, $json, $coerceQueryInput);
    }

    public static function commandJson(string $key, ?string $json = null): string
    {
        return self::execute(OperationType::COMMAND, $key, $json, coerceQueryInput: false);
    }

    private static function execute(OperationType $type, string $key, ?string $json, bool $coerceQueryInput): string
    {
        $input = $json === null ? null : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $configuration = new ServerConfiguration(coerceQueryInput: $coerceQueryInput);

        $results = [];
        foreach ([self::eagerRegistry(), self::cachedRegistry()] as $registry) {
            $server = new Server($registry, configuration: $configuration);
            $results[] = $type === OperationType::QUERY
                ? $server->query($key, $input, null, new NullClient())
                : $server->command($key, $input, null, new NullClient());
        }

        [$eagerResult, $cachedResult] = $results;
        $eagerJson = json_encode($eagerResult, JSON_THROW_ON_ERROR);
        $cachedJson = json_encode($cachedResult, JSON_THROW_ON_ERROR);

        expect($cachedResult->statusCode)->toBe($eagerResult->statusCode, "Cached registry status code diverges from the eager registry for {$key}");
        expect($cachedJson)->toBe($eagerJson, "Cached registry envelope diverges from the eager registry for {$key}");

        return $eagerJson;
    }

    private static function eagerRegistry(): EagerlyLoadedOperationRegistry
    {
        return self::$eagerRegistry ??= EagerlyLoadedOperationRegistry::eagerlyDiscover(
            __DIR__.'/Fixtures/Operations',
            keyGenerator: new PlainlyExposedKeyGenerator(),
        );
    }

    private static function cachedRegistry(): CachedOperationRegistry
    {
        if (self::$cachedRegistry !== null) {
            return self::$cachedRegistry;
        }

        $file = sys_get_temp_dir().'/php-ts-bindings-integration-'.getmypid().'.php';
        CachedOperationRegistry::writeToCache(self::eagerRegistry(), $file, idLength: 12);
        register_shutdown_function(static function () use ($file): void {
            @unlink($file);
        });

        return self::$cachedRegistry = require $file;
    }
}
