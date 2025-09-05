<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\KeyGenerators;

use Le0daniel\PhpTsBindings\Contracts\OperationKeyGenerator;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Utils\Hashs;

final class HashSha256KeyGenerator implements OperationKeyGenerator
{
    public function __construct(
        private readonly string $pepper,
        private readonly int $namespaceLength = 8,
        private readonly int $fnNameLength = 24,
    )
    {
    }

    public function generateKey(Definition $definition): string
    {
        $namespaceHash = Hashs::base64UrlEncodedSha256("{$definition->namespace}|{$this->pepper}");
        $fnHash = Hashs::base64UrlEncodedSha256("{$definition->name}|{$this->pepper}");

        $namespace = substr($namespaceHash, 0, $this->namespaceLength);
        $fnName = substr($fnHash, 0, $this->fnNameLength);

        return "{$namespace}.{$fnName}";
    }
}