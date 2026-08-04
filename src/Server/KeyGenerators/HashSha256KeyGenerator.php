<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\KeyGenerators;

use Le0daniel\PhpTsBindings\Contracts\OperationKeyGenerator;
use Le0daniel\PhpTsBindings\Utils\Hashs;
use Override;

final readonly class HashSha256KeyGenerator implements OperationKeyGenerator
{
    public function __construct(
        private string $pepper,
        private int    $namespaceLength = 8,
        private int    $fnNameLength = 24,
    )
    {
    }

    /**
     * The name segment is hashed over the namespace as well. Hashing it on its own gave every
     * `get` in the application the identical segment, so learning one key told you the segment for
     * that method name in every other namespace - the structure the obfuscation is meant to hide.
     */
    #[Override]
    public function generateKey(string $namespace, string $name): string
    {
        $namespaceHash = Hashs::base64UrlEncodedSha256("{$namespace}|{$this->pepper}");
        $fnHash = Hashs::base64UrlEncodedSha256("{$namespace}|{$name}|{$this->pepper}");

        $namespace = substr($namespaceHash, 0, $this->namespaceLength);
        $fnName = substr($fnHash, 0, $this->fnNameLength);

        return "{$namespace}.{$fnName}";
    }
}