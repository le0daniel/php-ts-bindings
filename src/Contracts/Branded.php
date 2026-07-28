<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

/**
 * Implemented by nodes that can carry a TypeScript brand, so the code generators do not have
 * to know which concrete node classes support branding.
 *
 * Brands are code generation metadata only: they have no runtime impact and are deliberately
 * not part of a node's __toString() or exportPhpCode(), so they do not survive the ASTOptimizer.
 */
interface Branded
{
    public function brandName(): ?string;
}
