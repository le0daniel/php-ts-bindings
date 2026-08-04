<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Typescript\Code;

use Closure;
use Le0daniel\PhpTsBindings\Typescript\Utils\Syntax;
use Le0daniel\PhpTsBindings\Utils\Lists;
use NoDiscard;
use Override;
use Stringable;

/**
 * A TypeScript source file being assembled: a set of imports and a body of code blocks.
 *
 * The file owns everything about imports that is not local to a single module. It merges the
 * modules that were named more than once, orders them, splits each into a type line and a value
 * line, and renders both. A caller only ever says what it needs and from where.
 *
 * Immutable: every method returns a new file, so the same instance can be appended to twice, kept
 * as a template, or shared between two outputs without one of them mutating the other.
 *
 * Rendering is deterministic — modules sorted by specifier, names sorted inside each line, exactly
 * one trailing newline — so the same inputs always produce the same bytes, whatever order the
 * generators ran in. Generated files are compared byte for byte to decide whether they are stale,
 * which is why this matters.
 *
 * Raw `import` lines written inside a code block are not detected or merged; use imports for
 * imports.
 *
 * Not modelled: default imports, namespace imports (`* as ns`), side effect imports
 * (`import './x';`), re-exports, and the inline type modifier (`import {type Foo, bar}`). Each is
 * an additive change: a new bucket on TypescriptImport plus a line in statementsFor().
 */
final readonly class TypescriptFile implements Stringable
{
    /**
     * Leading and trailing newlines stripped; blocks inside are separated by a blank line.
     */
    public string $code;

    /**
     * @var list<TypescriptImport> One entry per module, sorted by module specifier.
     */
    public array $imports;

    /**
     * @param list<TypescriptImport> $imports Duplicated modules are merged, empty ones dropped.
     */
    public function __construct(string $code = '', array $imports = [])
    {
        $this->code = self::normalizeBlock($code);
        $this->imports = self::mergeByModule($imports);
    }

    #[NoDiscard]
    public function withImports(TypescriptImport ...$imports): self
    {
        return new self($this->code, [...$this->imports, ...$imports] |> array_values(...));
    }

    /**
     * The same file with every module specifier rewritten. Imports are re-merged afterwards, so two
     * specifiers that resolve onto one module become one import rather than two lines naming the
     * same file.
     *
     * A specifier is written before it is known where the file writing it ends up, and what it has
     * to say depends on that. Whoever does know hands the rule in here.
     *
     * @param Closure(string): string $resolve
     */
    #[NoDiscard]
    public function withModulesResolvedBy(Closure $resolve): self
    {
        return new self($this->code, array_map(
            fn(TypescriptImport $import): TypescriptImport => new TypescriptImport(
                $resolve($import->from),
                $import->values,
                $import->types,
            ),
            $this->imports,
        ));
    }

    /**
     * Appends a block of code. A string carries no imports; a file carries its own, merged into
     * this one. Blocks are separated by a blank line, and appending nothing changes nothing.
     */
    #[NoDiscard]
    public function append(string|self $block): self
    {
        $code = $block instanceof self ? $block->code : self::normalizeBlock($block);
        $imports = $block instanceof self ? [...$this->imports, ...$block->imports] : $this->imports;

        return new self(
            $this->code === '' || $code === ''
                ? $this->code . $code
                : $this->code . PHP_EOL . PHP_EOL . $code,
            $imports,
        );
    }

    public function toString(): string
    {
        $importLines = [];
        foreach ($this->imports as $import) {
            array_push($importLines, ...self::statementsFor($import));
        }

        $body = $this->code === '' ? '' : $this->code . PHP_EOL;
        if ($importLines === []) {
            return $body;
        }

        return implode(PHP_EOL, $importLines) . PHP_EOL . ($body === '' ? '' : PHP_EOL . $body);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @param list<TypescriptImport> $imports
     * @return list<TypescriptImport>
     */
    private static function mergeByModule(array $imports): array
    {
        /** @var array<string, TypescriptImport> $byModule */
        $byModule = [];
        foreach ($imports as $import) {
            if ($import->isEmpty()) {
                continue;
            }

            $byModule[$import->from] = ($byModule[$import->from] ?? null)?->merge($import) ?? $import;
        }

        // SORT_STRING: a specifier that looks numeric would otherwise compare as a number.
        ksort($byModule, SORT_STRING);
        return array_values($byModule);
    }

    /**
     * @return list<string>
     */
    private static function statementsFor(TypescriptImport $import): array
    {
        return Lists::filterNullValues([
            self::statement('import type', $import->types, $import->from),
            self::statement('import', $import->values, $import->from),
        ]);
    }

    /**
     * @param list<string> $names
     */
    private static function statement(string $keyword, array $names, string $from): ?string
    {
        return $names === []
            ? null
            : "{$keyword} {" . implode(', ', $names) . '} from ' . Syntax::moduleSpecifier($from) . ';';
    }

    /**
     * A block owns its own indentation but not the blank lines around it — the file does.
     */
    private static function normalizeBlock(string $code): string
    {
        return trim($code) === '' ? '' : trim($code, "\r\n");
    }
}
