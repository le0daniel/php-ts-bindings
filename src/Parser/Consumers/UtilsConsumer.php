<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\OmitNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PickNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

final class UtilsConsumer implements TypeConsumer
{
    use InteractsWithGenerics;

    public function canConsume(ParserState $state): bool
    {
        return in_array($state->current()->value, ['Pick', 'Omit'], true);
    }

    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $type = $state->current()->value;
        $state->advance();

        [$nodeToPickFrom, $pick] = $this->consumeGenerics($state, $parser, 2, 2);

        if (!$nodeToPickFrom instanceof StructNode && !$nodeToPickFrom instanceof CustomCastingNode) {
            $state->produceSyntaxError("Expected struct or custom casting node for picking or omitting");
        }

        return match ($type) {
            'Pick' => new PickNode($nodeToPickFrom, $this->propertiesToPickOrOmit($state, $pick)),
            'Omit' => new OmitNode($nodeToPickFrom, $this->propertiesToPickOrOmit($state, $pick)),
            default => $state->produceSyntaxError("Expected Pick or Omit"),
        };
    }



    /**
     * @param ParserState $state
     * @param NodeInterface $node
     * @return list<string>
     * @throws InvalidSyntaxException
     */
    private function propertiesToPickOrOmit(ParserState $state, NodeInterface $node): array
    {
        if ($node instanceof LiteralNode && $node->type === LiteralType::STRING) {
            return [(string) $node->value];
        }

        if (!$node instanceof UnionNode) {
            $state->produceSyntaxError("Expected union node or string literal for picking or omitting");
        }

        /** @var UnionNode $node */

        return array_map(function (NodeInterface $node) use ($state): string {
            if ($node instanceof LiteralNode && $node->type === LiteralType::STRING) {
                return (string) $node->value;
            }

            $type = $node::class;
            $state->produceSyntaxError("Expected string literal for picking or omitting, got: {$type}");
        }, $node->types);
    }
}