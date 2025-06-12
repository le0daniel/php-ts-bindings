<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Parsers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Contracts\Parser;
use Le0daniel\PhpTsBindings\Parser\Definition\Token;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use RuntimeException;

final readonly class PhpStanGlobalsParser implements Parser
{
    /**
     * @param array<string, NodeInterface|string> $typeDeclarations
     */
    public function __construct(
        private array $typeDeclarations
    )
    {
    }

    public static function readGlobalConfigFile(string $path): self
    {
        return new self([]);
    }

    public function canParse(Token $token): bool
    {
        return array_key_exists($token->value, $this->typeDeclarations);
    }

    public function parse(Token $token, TypeParser $parser): NodeInterface
    {
        $node = $this->typeDeclarations[$token->value];
        if (!$node) {
            throw new RuntimeException("Could not find node for {$token->value}");
        }

        if (is_string($node)) {
            return $parser->parse($node);
        }

        return $node;
    }
}