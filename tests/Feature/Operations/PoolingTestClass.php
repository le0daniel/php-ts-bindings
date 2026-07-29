<?php declare(strict_types=1);

namespace Tests\Feature\Operations;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;

/**
 * Operations whose schemas are deliberately near-identical.
 *
 * Every operation's input and output AST is optimized into one shared registry, so schemas that
 * look alike but behave differently are exactly what can collide there. Registering these on the
 * real production path means a collision shows up as a behaviour change, not as a silent merge.
 */
final class PoolingTestClass
{
    /**
     * Same shape as constrainedEmail, but without the constraint.
     *
     * @param array{email: string} $data
     * @return array{email: string}
     */
    #[Command('pooling')]
    public function looseEmail(array $data): array
    {
        return ['email' => $data['email']];
    }

    /**
     * Same shape as looseEmail, but the constraint must survive pooling.
     *
     * @param array{email: non-empty-string} $data
     * @return array{email: string}
     */
    #[Command('pooling')]
    public function constrainedEmail(array $data): array
    {
        return ['email' => $data['email']];
    }

    /**
     * Declares its properties in non-alphabetical order; key order must match the uncached path.
     *
     * @param array{zebra: string, alpha: string, middle: int} $data
     * @return array{zebra: string, alpha: string, middle: int}
     */
    #[Command('pooling')]
    public function declarationOrder(array $data): array
    {
        return $data;
    }

    /**
     * The same shape as declarationOrder, declared in a different order. Both must resolve to the
     * same interned entry without either changing behaviour.
     *
     * @param array{alpha: string, middle: int, zebra: string} $data
     * @return array{alpha: string, middle: int, zebra: string}
     */
    #[Command('pooling')]
    public function reversedOrder(array $data): array
    {
        return $data;
    }

    /**
     * @param array{value: 1|2} $data
     * @return array{value: int}
     */
    #[Command('pooling')]
    public function intLiteral(array $data): array
    {
        return ['value' => $data['value']];
    }

    /**
     * Float literals that stringify like the integer ones above.
     *
     * @param array{value: 1.0|2.0} $data
     * @return array{value: float}
     */
    #[Command('pooling')]
    public function floatLiteral(array $data): array
    {
        return ['value' => $data['value']];
    }
}
