<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

final readonly class Regexes
{
    private const array OPENING_BRACKETS = ['{' => true, '[' => true, '(' => true, '<' => true];
    private const array CLOSING_BRACKETS = ['}' => true, ']' => true, ')' => true, '>' => true];

    /** Characters that leave a type expression unfinished, so it continues after a line break. */
    private const array CONTINUING_CHARS = ['|' => true, '&' => true, ':' => true, ',' => true];

    public static function findFirstVarDeclaration(string $docBlocks): ?string
    {
        return self::findTypeOfTag($docBlocks, 'var');
    }

    public static function findReturnTypeDeclaration(string $docBlocks): ?string
    {
        return self::findTypeOfTag($docBlocks, 'return');
    }

    public static function findParamWithNameDeclaration(string $docBlocks, string $paramName): ?string
    {
        $variableRegex = '/^(?:[&.]|\s)*\$' . preg_quote($paramName, '/') . '(?![a-zA-Z0-9_\x80-\xff])/';

        foreach (self::tags($docBlocks) as [$tagName, $body]) {
            if ($tagName !== 'param') {
                continue;
            }

            [$type, $rest] = self::splitLeadingType($body);
            if ($type === null || preg_match($variableRegex, $rest) !== 1) {
                continue;
            }

            return $type;
        }
        return null;
    }

    private static function findTypeOfTag(string $docBlocks, string $tagName): ?string
    {
        foreach (self::tags($docBlocks) as [$name, $body]) {
            if ($name !== $tagName) {
                continue;
            }

            if ($type = self::splitLeadingType($body)[0]) {
                return $type;
            }
        }
        return null;
    }

    /**
     * Splits a doc block into its tags, joining continuation lines with a single space so that
     * multiline declarations (array shapes spanning multiple lines) stay intact.
     *
     * @return list<array{0: string, 1: string}> Tuples of tag name and tag body.
     */
    private static function tags(string $docBlock): array
    {
        $tags = [];
        $current = null;

        foreach (self::lines($docBlock) as $line) {
            $matches = [];
            if (preg_match('/^@(?<name>[a-zA-Z][a-zA-Z0-9-]*)\s*(?<body>.*)$/', $line, $matches) === 1) {
                if ($current) {
                    $tags[] = $current;
                }
                $current = [$matches['name'], $matches['body']];
                continue;
            }

            // A blank line ends the current tag, everything after it is free-form documentation.
            if ($line === '') {
                if ($current) {
                    $tags[] = $current;
                }
                $current = null;
                continue;
            }

            if ($current) {
                $current[1] = trim("{$current[1]} {$line}");
            }
        }

        if ($current) {
            $tags[] = $current;
        }

        return $tags;
    }

    /** @return list<string> The doc block lines, stripped of their comment delimiters. */
    private static function lines(string $docBlock): array
    {
        $withoutDelimiters = preg_replace('#^\s*/\*\*?|\*/\s*$#', '', $docBlock) ?? $docBlock;
        $lines = preg_split('/\R/', $withoutDelimiters);
        return array_map(
            static fn(string $line): string => trim(preg_replace('/^\s*\*/', '', $line) ?? $line),
            $lines === false ? [$withoutDelimiters] : $lines,
        );
    }

    /**
     * Takes the leading type expression off a tag body, honoring nesting and string literals, and
     * returns it together with the remainder (the variable name and/or the description).
     *
     * @return array{0: string|null, 1: string}
     */
    private static function splitLeadingType(string $body): array
    {
        $length = strlen($body);
        $quote = null;
        $depth = 0;

        for ($index = 0; $index < $length; $index++) {
            $char = $body[$index];

            if ($quote) {
                match (true) {
                    $char === '\\' => $index++,
                    $char === $quote => $quote = null,
                    default => null,
                };
                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
                continue;
            }

            if (isset(self::OPENING_BRACKETS[$char])) {
                $depth++;
                continue;
            }

            if (isset(self::CLOSING_BRACKETS[$char])) {
                $depth = max(0, $depth - 1);
                continue;
            }

            if ($depth > 0 || !ctype_space($char)) {
                continue;
            }

            $nextIndex = $index + strspn($body, " \t\n\r\v\f", $index);
            if (self::typeContinues($body, $index, $nextIndex)) {
                $index = $nextIndex - 1;
                continue;
            }

            $type = rtrim(substr($body, 0, $index));
            return [$type === '' ? null : $type, ltrim(substr($body, $nextIndex))];
        }

        $type = trim($body);
        return [$type === '' ? null : $type, ''];
    }

    /**
     * Decides whether the whitespace at $index is part of the type or ends it. Whitespace is only
     * ever internal to a type when a union or intersection is being built up.
     */
    private static function typeContinues(string $body, int $index, int $nextIndex): bool
    {
        if ($nextIndex >= strlen($body)) {
            return false;
        }

        if ($index > 0 && isset(self::CONTINUING_CHARS[$body[$index - 1]])) {
            return true;
        }

        $next = $body[$nextIndex];
        if ($next !== '|' && $next !== '&') {
            return false;
        }

        // "string &$reference" and "string &...$references" are by-reference parameters, not
        // intersection types.
        return ($body[$nextIndex + 1] ?? '') !== '$' && ($body[$nextIndex + 1] ?? '') !== '.';
    }
}
