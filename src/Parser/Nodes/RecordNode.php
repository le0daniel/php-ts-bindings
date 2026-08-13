<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\ValidatableNode;
use Le0daniel\PhpTsBindings\Parser\Contracts\WrapsNode;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Parser\Helpers\RecordKey;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

/**
 * Every `array<...>` is a record: a JSON object on the wire, whatever its keys happen to look
 * like. The key is a node of its own rather than an assumed `string`, so a literal key set
 * (`array<'draft'|'live', int>`) and a refined one (`array<positive-int, V>`) both survive to the
 * generator and to the executor. What may stand here is decided in one place, RecordKey.
 *
 * WrapsNode still exposes the value: a record is a collection of $node, keyed. Callers that walk
 * the tree exhaustively - AstValidator, ASTOptimizer - read $keyNode explicitly.
 */
final readonly class RecordNode implements NodeInterface, ValidatableNode, WrapsNode
{
    public function __construct(
        public NodeInterface $keyNode,
        public NodeInterface $node,
    ) {
    }

    /**
     * The parser rejects an unusable key with a syntax error pointing at the offending token, which
     * is the better message and covers every schema that comes from a docblock. This covers the
     * rest: a hand built AST cannot describe a record keyed by something PHP could not put in front
     * of `=>`, because the executor would have no way to honour it.
     */
    #[Override]
    public function validate(): void
    {
        if (! RecordKey::isUsableAsKey($this->keyNode)) {
            throw new ParserException(
                "A record key must be 'string', 'int' or a union of string/int literals. Got: {$this->keyNode}"
            );
        }
    }

    #[Override]
    public function __toString(): string
    {
        return "array<{$this->keyNode},{$this->node}>";
    }

    #[Override]
    public function exportPhpCode(): string
    {
        $classname = PHPExport::absolute(self::class);
        $exportedKey = PHPExport::export($this->keyNode);
        $exportedType = PHPExport::export($this->node);

        return "new {$classname}({$exportedKey},{$exportedType})";
    }
}
