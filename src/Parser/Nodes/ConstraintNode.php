<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Parser\Contracts\Constraint;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\WrapsNode;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

final readonly class ConstraintNode implements NodeInterface, WrapsNode
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

    public function areConstraintsFulfilled(mixed $value, ExecutionContext $context): bool
    {
        return array_all($this->constraints, fn(Constraint $constraint) => $constraint->validate($value, $context));
    }

    #[Override]
    public function __toString(): string
    {
        if (count($this->constraints) === 0) {
            return $this->node->__toString();
        }

        // Each constraint names its own bounds, so `int<0, 100>` reads as `int & IntRange(0, 100)`
        // rather than losing the numbers to a bare class name.
        $names = implode(', ', array_map(
            static fn(Constraint $constraint): string => (string)$constraint,
            $this->constraints,
        ));

        return "{$this->node} & {$names}";
    }

    #[Override]
    public function exportPhpCode(): string
    {
        if (count($this->constraints) === 0) {
            return $this->node->exportPhpCode();
        }

        $className = PHPExport::absolute(self::class);
        $node = $this->node->exportPhpCode();
        $constraints = PHPExport::exportArray($this->constraints);
        return "new {$className}({$node},{$constraints})";
    }
}