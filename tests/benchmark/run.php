<?php

declare(strict_types=1);

/**
 * Times the cached operation registry against eager discovery over the integration fixtures:
 * one-off cache codegen, registry boot, resolving every schema, and steady-state request
 * execution. Prints one summary table, eager vs cached side by side. Run it through
 * `XDEBUG_MODE=off composer benchmark`.
 *
 * Methodology, and why the numbers are indicative rather than rigorous:
 * - Single process, wall clock via hrtime(); the median is the headline number.
 * - CLI opcache is off by default, so the cached boot recompiles the generated file on every
 *   iteration; production serves it from opcache and is ~2000x cheaper. To see that, run
 *   `php -d opcache.enable_cli=1 -d opcache.file_update_protection=0 tests/benchmark/run.php` -
 *   without the second flag opcache silently refuses the cache file because this script writes
 *   it moments before requiring it (production files written at deploy time are unaffected).
 * - Both registries memoize Operation instances, so boot and warm-all build a fresh registry per
 *   iteration while steady-state deliberately reuses warm ones.
 * - Warm-all includes boot; warm-all minus boot approximates pure schema resolution cost.
 * - Request rows on warm registries should be near parity: both paths execute the same node
 *   graph. A large eager/cached gap there means the eager path is re-resolving (re-parsing)
 *   schemas per call instead of serving memoized nodes.
 */

use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Server\Operations\CachedOperationRegistry;
use Le0daniel\PhpTsBindings\Server\Server;
use Tests\Integration\IntegrationHarness;

require __DIR__.'/../../vendor/autoload.php';

const WARMUP_BOOT = 3;
const SAMPLES_BOOT = 30;
const WARMUP_STEADY = 50;
const SAMPLES_STEADY = 500;

/**
 * @param  callable(): mixed  $fn
 * @return array{min: float, median: float, mean: float} milliseconds
 */
function measure(int $warmup, int $samples, callable $fn): array
{
    for ($i = 0; $i < $warmup; $i++) {
        $fn();
    }

    $times = [];
    for ($i = 0; $i < $samples; $i++) {
        $start = hrtime(true);
        $fn();
        $times[] = (hrtime(true) - $start) / 1_000_000;
    }
    sort($times);

    return [
        'min' => $times[0],
        'median' => $times[intdiv($samples, 2)],
        'mean' => array_sum($times) / $samples,
    ];
}

printf("PHP %s | opcache.enable_cli=%s\n", PHP_VERSION, ini_get('opcache.enable_cli') === '1' ? 'on' : 'off');
if (ini_get('opcache.enable_cli') === '1' && (int) ini_get('opcache.file_update_protection') > 0) {
    echo "NOTE: opcache.file_update_protection > 0 keeps opcache from caching the freshly\n";
    echo "      written cache file - add -d opcache.file_update_protection=0 for real hits.\n";
}
if (extension_loaded('xdebug') && getenv('XDEBUG_MODE') !== 'off') {
    echo "WARNING: Xdebug is active and distorts every number below.\n";
    echo "         Re-run as: XDEBUG_MODE=off composer benchmark\n";
}

$cacheFile = sys_get_temp_dir().'/php-ts-bindings-bench-'.getmypid().'.php';
register_shutdown_function(static function () use ($cacheFile): void {
    @unlink($cacheFile);
});

// Untimed seed build: warms the autoloader (fixtures, parser, executor classes) so the first
// timed iteration does not pay for class loading.
$seed = IntegrationHarness::discoverEagerRegistry();

$start = hrtime(true);
CachedOperationRegistry::writeToCache($seed, $cacheFile, idLength: IntegrationHarness::CACHE_ID_LENGTH);
$codegenMs = (hrtime(true) - $start) / 1_000_000;
$operationCount = count($seed->all());

printf("%d operations | cache file %d bytes | codegen: writeToCache() one-off %.1fms\n", $operationCount, filesize($cacheFile) ?: 0, $codegenMs);

/** @var list<array{string, array{min: float, median: float, mean: float}, array{min: float, median: float, mean: float}}> $results */
$results = [];

$results[] = [
    'boot: construct registry, schemas unresolved',
    measure(WARMUP_BOOT, SAMPLES_BOOT, static fn (): mixed => IntegrationHarness::discoverEagerRegistry()),
    measure(WARMUP_BOOT, SAMPLES_BOOT, static fn (): mixed => require $cacheFile),
];

$warmAll = static function (callable $boot): void {
    foreach ($boot()->all() as $operation) {
        $operation->inputNode();
        $operation->outputNode();
    }
};
$results[] = [
    "warm-all: resolve all {$operationCount} operation schemas",
    measure(WARMUP_BOOT, SAMPLES_BOOT, static fn (): mixed => $warmAll(static fn (): mixed => IntegrationHarness::discoverEagerRegistry())),
    measure(WARMUP_BOOT, SAMPLES_BOOT, static fn (): mixed => $warmAll(static fn (): mixed => require $cacheFile)),
];

$configuration = new ServerConfiguration();
$client = new NullClient();
$servers = [
    'eager' => new Server(IntegrationHarness::discoverEagerRegistry(), configuration: $configuration),
    'cached' => new Server(require $cacheFile, configuration: $configuration),
];

$requests = [
    ['inventory.convertWeight', 'query', '2.5', 'bare scalar'],
    ['cart.addItem', 'command', '{"item":{"sku":"ABC-123","quantity":2,"note":"engrave"}}', 'nested struct + castables'],
    ['orders.getOrder', 'query', '{"orderNumber":"ORD-1001"}', 'serialization-heavy'],
    ['catalog.feedEvents', 'query', '{"events":[{"kind":"restock","qty":5},{"kind":"sale","ref":"S-1"}]}', 'union in list'],
];

foreach ($requests as [$key, $type, $json, $blurb]) {
    $input = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    $stats = [];
    foreach ($servers as $name => $server) {
        $call = $type === 'query'
            ? static fn (): mixed => $server->query($key, $input, null, $client)
            : static fn (): mixed => $server->command($key, $input, null, $client);

        $result = $call();
        if ($result->statusCode !== 200) {
            fwrite(STDERR, "Aborting: {$key} returned status {$result->statusCode} on the {$name} registry - this would benchmark an error path.\n");
            exit(1);
        }

        $stats[$name] = measure(WARMUP_STEADY, SAMPLES_STEADY, $call);
    }
    $results[] = ["{$type} {$key} - {$blurb}", $stats['eager'], $stats['cached']];
}

printf("\n%-50s %11s %11s %11s %11s %9s\n", 'scenario', 'eager min', 'eager med', 'cached min', 'cached med', 'speedup');
echo str_repeat('-', 108), PHP_EOL;
foreach ($results as [$label, $eager, $cached]) {
    printf(
        "%-50s %9.4fms %9.4fms %9.4fms %9.4fms %9s\n",
        $label,
        $eager['min'],
        $eager['median'],
        $cached['min'],
        $cached['median'],
        sprintf('x%.1f', $eager['median'] / $cached['median']),
    );
}

echo PHP_EOL;
echo 'Speedup = eager median / cached median; < 1.0 means the cached path is slower.', PHP_EOL;
echo 'boot/warm-all: fresh registry per iteration ('.SAMPLES_BOOT.' samples). Requests: warm registries ('.SAMPLES_STEADY.' samples).', PHP_EOL;
echo 'Request rows should be near parity - a large gap means eager re-resolves schemas per call.', PHP_EOL;
