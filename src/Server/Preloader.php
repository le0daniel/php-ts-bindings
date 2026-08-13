<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server;

use Le0daniel\PhpTsBindings\Contracts\OperationKeyGenerator;
use Le0daniel\PhpTsBindings\Executor\Exceptions\SchemaException;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\NullNode;
use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;
use Le0daniel\PhpTsBindings\Utils\Strings;
use UnitEnum;

final readonly class Preloader
{
    /**
     * It's critical that the key generator is the same as the Key generator used by the server's repository.
     * Why? Because this is how the key is actually derived from the namespace and name.
     */
    public function __construct(
        private Server $server,
        private OperationKeyGenerator $keyGenerator,
    ) {
    }

    /**
     * Execute a query and returns it's result.
     * The query is simply executed and the result serialized.
     *
     * This is really useful if you want to preload data on the server on a page load and make it instantly available on the
     * client side.
     *
     * @return array{response: mixed, queryKey: list<mixed>}
     */
    public function preload(string|UnitEnum $namespace, string $name, mixed $input, mixed $context): array
    {
        $namespaceAsString = Strings::toString($namespace);
        $key = $this->keyGenerator->generateKey($namespaceAsString, $name);
        $result = $this->server->query($key, $input, $context, new NullClient());

        if (! $result instanceof RpcSuccess) {
            throw new SchemaException("Failed to preload: {$namespaceAsString}.{$name}");
        }

        return [
            'response' => $result->data,
            'queryKey' => $this->queryKey($namespaceAsString, $name, $input),
        ];
    }

    /**
     * Must be the key the generated `queryKey()` builds, or a seeded cache never matches and the
     * client refetches what was just preloaded.
     *
     * Whether the input is part of the key is a property of the schema, not of the value: the
     * generated code appends it whenever the operation has an input at all. Deciding on
     * `$input === null` instead meant a nullable-input query preloaded with null produced a
     * two-element key against the client's three-element one.
     *
     * @return list<mixed>
     */
    private function queryKey(string $namespace, string $name, mixed $input): array
    {
        $operation = $this->server->registry->get(OperationType::QUERY, $this->keyGenerator->generateKey($namespace, $name));

        return $operation->inputNode() instanceof NullNode
            ? [$namespace, $name]
            : [$namespace, $name, $input];
    }

    /**
     * @param  list<array{namespace: string|UnitEnum, name: string, input: mixed}>  $preloads
     * @return list<array{response: mixed, queryKey: list<mixed>}>
     */
    public function preloadMany(array $preloads, mixed $context): array
    {
        return array_map(
            fn (array $preload) => $this->preload($preload['namespace'], $preload['name'], $preload['input'], $context),
            $preloads
        );
    }
}
