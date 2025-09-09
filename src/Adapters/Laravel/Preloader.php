<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel;

use Le0daniel\PhpTsBindings\Contracts\OperationKeyGenerator;
use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;
use Le0daniel\PhpTsBindings\Server\Server;
use Le0daniel\PhpTsBindings\Utils\Strings;
use RuntimeException;
use UnitEnum;

final readonly class Preloader
{
    /**
     * It's critical that the key generator is the same as the Key generator used by the server's repository.
     * Why? Because this is how the key is actually derived from the namespace and name.
     *
     * @param Server $server
     * @param OperationKeyGenerator $keyGenerator
     */
    public function __construct(
        private Server                $server,
        private OperationKeyGenerator $keyGenerator,
    )
    {
    }

    /**
     * Execute a query and returns it's result.
     * The query is simply executed and the result serialized.
     *
     * This is really useful if you want to preload data on the server on a page load and make it instantly available on the
     * client side.
     *
     * @return array{result: mixed, key: list<mixed>}
     */
    public function preload(string|UnitEnum $namespace, string $name, mixed $input, mixed $context): array
    {
        $namespaceAsString = Strings::toString($namespace);
        $fqcn = $this->keyGenerator->generateKey(Strings::toString($namespaceAsString), $name);
        $result = $this->server->query($fqcn, $input, $context, new NullClient());

        if (!$result instanceof RpcSuccess) {
            throw new RuntimeException("Failed to preload: {$namespaceAsString}.{$name}");
        }

        return [
            'result' => $result->data,
            'key' => [$namespaceAsString, $name, $input]
        ];
    }

    /**
     * @param list<array{namespace: string|UnitEnum, name: string, input: mixed}> $preloads
     * @param mixed $context
     * @return list<array{result: mixed, key: list<mixed>}>
     */
    public function preloadMany(array $preloads, mixed $context): array
    {
        return array_map(
            fn(array $preload) => $this->preload($preload['namespace'], $preload['name'], $preload['input'], $context),
            $preloads
        );
    }
}