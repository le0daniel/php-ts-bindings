<?php

namespace Le0daniel\PhpTsBindings\Parser\Contracts;

use Le0daniel\PhpTsBindings\Contracts\ExportableToPhpCode;
use Stringable;

/**
 * exportPhpCode() is a node's identity. The ASTOptimizer interns nodes by hashing it: two nodes
 * that export the same PHP construct the same object, so sharing one instance between them is
 * correct by definition rather than by convention.
 *
 * INVARIANT: every constructor argument that changes runtime behaviour MUST appear in
 * exportPhpCode(). Anything omitted is, by definition, information the cache discards — that is
 * the escape hatch MetadataNode uses deliberately, exporting nothing of itself so codegen
 * metadata can never reach a cached AST.
 *
 * __toString() is a human readable type label for diagnostics and error messages. It is allowed
 * to be lossy and MUST NOT be used as a cache key.
 */
interface NodeInterface extends Stringable, ExportableToPhpCode
{
}