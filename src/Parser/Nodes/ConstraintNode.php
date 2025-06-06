<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Le0daniel\PhpTsBindings\Validators\Constraint;

final readonly class ConstraintNode implements NodeInterface
{
    /**
     * @param NodeInterface $node
     * @param list<Constraint> $constraints
     */
    public function __construct(
        public NodeInterface $node,
        public array         $constraints,
    )
    {
    }

    public function areConstraintsFulfilled(mixed $value, $context): bool
    {
        return array_all($this->constraints, fn($constraint) => $constraint->validate($value, $context));
    }

    public function __toString(): string
    {
        return $this->node->__toString();
    }

    public function exportPhpCode(): string
    {
        if (empty($this->constraints)) {
            return $this->node->exportPhpCode();
        }

        $className = PHPExport::absolute(self::class);
        $node = $this->node->exportPhpCode();
        $constraints = PHPExport::exportArray($this->constraints);
        return "new {$className}({$node},{$constraints})";
    }
}