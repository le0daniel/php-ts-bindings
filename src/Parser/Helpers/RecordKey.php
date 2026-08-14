<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Helpers;

use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\IntNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\StringNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Utils\Nodes;

/**
 * What may stand as the key of a RecordNode, and what the rest of the library needs to know about
 * it. PHP array keys are their own small type system - `int|string`, with numeric strings folded
 * into ints - and every question asked of a key here has an answer in that system.
 *
 * Each predicate looks through metadata and constraints via getDeclaringNode(). A brand
 * (`array<BrandedString<'k'>, V>`) and a refinement (`array<non-empty-string, V>`) do not change
 * what a key *is*; they only add a runtime check the executor runs on top of it.
 */
final readonly class RecordKey
{
    /**
     * A key has to be something PHP can actually put in front of `=>`. `string` and `int` cover
     * the open sets, a string or int literal names one key, and a union composes them. Everything
     * else - an enum case, a value object, a bool, a shape - has no array-key form in PHP either,
     * so it is rejected at parse time rather than failing per entry at runtime.
     */
    public static function isUsableAsKey(NodeInterface $node): bool
    {
        $declaring = Nodes::getDeclaringNode($node);

        return match (true) {
            $declaring instanceof StringNode, $declaring instanceof IntNode => true,
            $declaring instanceof LiteralNode => self::isKeyLiteral($declaring),
            $declaring instanceof UnionNode => array_all($declaring->nodes, self::isUsableAsKey(...)),
            default => false,
        };
    }

    /**
     * Whether the key set is known in full - every arm names one key. Only then can TypeScript say
     * more than `string`, and only then is `Partial<Record<...>>` the honest emission.
     */
    public static function isClosedKeySet(NodeInterface $node): bool
    {
        $declaring = Nodes::getDeclaringNode($node);

        return match (true) {
            $declaring instanceof LiteralNode => self::isKeyLiteral($declaring),
            $declaring instanceof UnionNode => array_all($declaring->nodes, self::isClosedKeySet(...)),
            default => false,
        };
    }

    /**
     * The literal, as a JSON object key spells it. An int literal is included: `array<1|2, V>`
     * arrives as `{"1": ...}`, so the key set is `"1"|"2"` for the same reason `array<int, V>` is
     * `Record<string, V>`.
     */
    public static function literalKeyValue(LiteralNode $node): string
    {
        $value = $node->value;
        assert(is_string($value) || is_int($value));

        return (string) $value;
    }

    /**
     * A string literal is only a key PHP can hold if PHP would not fold it into an int first. It
     * folds a canonical decimal integer and nothing else, so `'01'`, `' 1'` and `'+1'` are all
     * genuine string keys while `'1'` is not one at all - `array<'1'|'2', V>` describes a set of
     * keys no PHP array can contain, and would silently match nothing.
     */
    private static function isKeyLiteral(LiteralNode $node): bool
    {
        return match ($node->type) {
            LiteralType::INT => true,
            LiteralType::STRING => ! self::foldsToInt($node->stringValue()),
            default => false,
        };
    }

    /**
     * PHP's own rule for turning a string array key into an int, expressed the way PHP applies it:
     * the key survives the round trip through int only when it was canonical to begin with. That
     * rules out leading zeros, a leading '+', surrounding whitespace, '-0', and anything wider
     * than the platform int.
     */
    private static function foldsToInt(string $value): bool
    {
        return (string) (int) $value === $value;
    }
}
